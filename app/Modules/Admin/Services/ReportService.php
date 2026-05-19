<?php

namespace App\Modules\Admin\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\{Cache, DB, Storage};
use Illuminate\Pagination\LengthAwarePaginator;

class ReportService
{
    // ── Revenue Report ────────────────────────────────────────────────────────

    public function getRevenueReport(array $filters): array
    {
        $cacheKey = 'admin:report:revenue:' . md5(serialize($filters));

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($filters) {
            $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
            $dateTo   = $filters['date_to']   ?? now()->toDateString();
            $groupBy  = $filters['group_by']  ?? 'day';

            $trunc = match ($groupBy) {
                'week'  => "date_trunc('week', o.created_at)::date",
                'month' => "date_trunc('month', o.created_at)::date",
                default => "o.created_at::date",
            };

            $summary = DB::connection('pgsql')
                ->table('orders as o')
                ->whereNull('o.deleted_at')
                ->whereIn('o.status', ['confirmed', 'processing', 'shipped', 'delivered', 'completed'])
                ->whereBetween(DB::raw('o.created_at::date'), [$dateFrom, $dateTo])
                ->when($filters['user_type'] ?? null,
                    fn($q, $v) => $q->join('users as u', 'u.id', '=', 'o.user_id')
                        ->where('u.user_type', $v)
                )
                ->selectRaw("
                    {$trunc}                   AS period,
                    COUNT(*)                    AS order_count,
                    SUM(o.subtotal)             AS subtotal,
                    SUM(o.discount_amount + o.coupon_discount + o.points_discount) AS total_discount,
                    SUM(o.shipping_fee)         AS shipping_revenue,
                    SUM(o.total)                AS gross_revenue,
                    AVG(o.total)                AS avg_order_value
                ")
                ->groupByRaw("1")
                ->orderByRaw("1 ASC")
                ->get();

            $totals = DB::connection('pgsql')
                ->table('orders as o')
                ->whereNull('o.deleted_at')
                ->whereIn('o.status', ['confirmed', 'processing', 'shipped', 'delivered', 'completed'])
                ->whereBetween(DB::raw('o.created_at::date'), [$dateFrom, $dateTo])
                ->selectRaw("
                    COUNT(*)        AS total_orders,
                    SUM(total)      AS total_revenue,
                    AVG(total)      AS avg_order_value,
                    SUM(discount_amount + coupon_discount + points_discount) AS total_discounts
                ")
                ->first();

            return [
                'summary' => $summary->map(fn($r) => [
                    'period'         => $r->period,
                    'order_count'    => (int)   $r->order_count,
                    'subtotal'       => (float) $r->subtotal,
                    'total_discount' => (float) $r->total_discount,
                    'shipping'       => (float) $r->shipping_revenue,
                    'gross_revenue'  => (float) $r->gross_revenue,
                    'avg_order_value'=> (float) $r->avg_order_value,
                ])->all(),
                'totals' => [
                    'total_orders'   => (int)   $totals->total_orders,
                    'total_revenue'  => (float) $totals->total_revenue,
                    'avg_order_value'=> (float) $totals->avg_order_value,
                    'total_discounts'=> (float) $totals->total_discounts,
                ],
                'filters'    => $filters,
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    // ── Product Sales Report ──────────────────────────────────────────────────

    public function getProductSalesReport(array $filters): LengthAwarePaginator
    {
        // Paginator mengandung koneksi DB — TIDAK bisa di-cache.
        // Cache hanya cocok untuk hasil query yang sudah diselesaikan (array/Collection).
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo   = $filters['date_to']   ?? now()->toDateString();

        return DB::connection('pgsql')
            ->table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->whereNull('o.deleted_at')
            ->whereIn('o.status', ['confirmed', 'processing', 'shipped', 'delivered', 'completed'])
            ->whereBetween(DB::raw('o.created_at::date'), [$dateFrom, $dateTo])
            ->when($filters['category_id'] ?? null,
                fn($q, $v) => $q->where('p.category_id', $v))
            ->selectRaw('
                p.id,
                p.name              AS product_name,
                c.name              AS category_name,
                SUM(oi.quantity)    AS total_qty,
                SUM(oi.subtotal)    AS total_revenue,
                AVG(oi.unit_price)  AS avg_price,
                COUNT(DISTINCT o.user_id) AS unique_buyers
            ')
            ->groupBy('p.id', 'p.name', 'c.name')
            ->orderByDesc('total_revenue')
            ->paginate($filters['per_page'] ?? 20)
            ->through(fn($r) => [
                'id'             => $r->id,
                'product_name'   => $r->product_name,
                'category_name'  => $r->category_name,
                'total_qty'      => (int)   $r->total_qty,
                'total_revenue'  => (float) $r->total_revenue,
                'avg_price'      => (float) $r->avg_price,
                'unique_buyers'  => (int)   $r->unique_buyers,
            ]);
    }

    // ── User Report ───────────────────────────────────────────────────────────

    public function getUserReport(array $filters): LengthAwarePaginator
    {
        $dateFrom = $filters['date_from'] ?? now()->subDays(30)->toDateString();
        $dateTo   = $filters['date_to']   ?? now()->toDateString();

        return DB::connection('pgsql')
            ->table('users as u')
            ->leftJoin('orders as o', function ($j) use ($dateFrom, $dateTo) {
                $j->on('o.user_id', '=', 'u.id')
                    ->whereBetween(DB::raw('o.created_at::date'), [$dateFrom, $dateTo])
                    ->whereNull('o.deleted_at')
                    ->whereIn('o.status', ['completed']);
            })
            ->leftJoin('reward_points as rp', 'rp.user_id', '=', 'u.id')
            ->whereNull('u.deleted_at')
            ->when($filters['user_type'] ?? null, fn($q, $v) => $q->where('u.user_type', $v))
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('u.status', $v))
            ->selectRaw('
                u.id, u.name, u.email, u.user_type, u.status,
                u.created_at,
                COUNT(o.id)         AS total_orders,
                COALESCE(SUM(o.total), 0) AS total_spent,
                COALESCE(rp.balance, 0)   AS reward_balance
            ')
            ->groupBy('u.id', 'u.name', 'u.email', 'u.user_type', 'u.status', 'u.created_at', 'rp.balance')
            ->orderByDesc('total_spent')
            ->paginate($filters['per_page'] ?? 20);
    }

    // ── CSV Export (async via queue) ──────────────────────────────────────────

    public function queueExport(string $type, array $filters, string $requestedBy): string
    {
        $exportId = \Str::uuid()->toString();

        // Simpan kepemilikan export untuk mencegah IDOR (berlaku 24 jam)
        Cache::put("admin:export_owner:{$exportId}", $requestedBy, now()->addHours(24));

        \App\Jobs\GenerateAdminExport::dispatch($exportId, $type, $filters, $requestedBy)
            ->onQueue('default');

        return $exportId;
    }

    public function getExportDownloadUrl(string $exportId, string $requestedBy): ?string
    {
        // Validasi kepemilikan export
        $ownerId = Cache::get("admin:export_owner:{$exportId}");

        if ($ownerId !== $requestedBy) {
            throw new AuthorizationException('Anda tidak memiliki akses ke export ini.');
        }

        $path = "exports/admin/{$exportId}.csv";

        if (! Storage::disk('s3')->exists($path)) {
            return null;
        }

        // Pre-signed URL berlaku 15 menit
        return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(15));
    }

    // ── Inline CSV Stream (untuk export kecil < 10rb baris) ──────────────────

    public function streamRevenueCsv(array $filters): \Generator
    {
        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo   = $filters['date_to']   ?? now()->toDateString();

        // Header
        yield implode(',', ['order_number', 'date', 'user_name', 'user_type',
                'subtotal', 'discount', 'shipping', 'total', 'status', 'payment_method']) . "\n";

        $query = DB::connection('pgsql')
            ->table('orders as o')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('payments as p', 'p.order_id', '=', 'o.id')
            ->whereNull('o.deleted_at')
            ->whereBetween(DB::raw('o.created_at::date'), [$dateFrom, $dateTo])
            ->select([
                'o.order_number', 'o.created_at', 'u.name as user_name', 'u.user_type',
                'o.subtotal', DB::raw('(o.discount_amount + o.coupon_discount + o.points_discount) as discount'),
                'o.shipping_fee', 'o.total', 'o.status', 'p.method as payment_method',
            ])
            ->orderBy('o.created_at');

        foreach ($query->cursor() as $row) {
            yield implode(',', [
                    $this->sanitizeCsvValue($row->order_number),
                    $row->created_at,
                    '"' . str_replace('"', '""', $this->sanitizeCsvValue($row->user_name)) . '"',
                    $this->sanitizeCsvValue($row->user_type),
                    $row->subtotal,
                    $row->discount,
                    $row->shipping_fee,
                    $row->total,
                    $this->sanitizeCsvValue($row->status),
                    $this->sanitizeCsvValue($row->payment_method ?? ''),
                ]) . "\n";
        }
    }

    /**
     * Mencegah CSV Injection dengan mem-prefix karakter formula
     * (=, +, -, @) menggunakan tab agar tidak dieksekusi oleh spreadsheet.
     */
    private function sanitizeCsvValue(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        if (in_array($value[0] ?? '', ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "\t" . $value;
        }

        return $value;
    }
}
