<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{DB, Notification, Storage};

class GenerateAdminExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue   = 'default';
    public int    $timeout = 300; // 5 menit

    public function __construct(
        private readonly string $exportId,
        private readonly string $type,
        private readonly array  $filters,
        private readonly string $requestedBy,
    ) {}

    public function handle(): void
    {
        $path = "exports/admin/{$this->exportId}.csv";
        $temp = tempnam(sys_get_temp_dir(), 'export_');

        try {
            $handle = fopen($temp, 'w');

            match ($this->type) {
                'revenue' => $this->writeRevenueCsv($handle),
                'orders'  => $this->writeOrdersCsv($handle),
                'products'=> $this->writeProductsCsv($handle),
                'users'   => $this->writeUsersCsv($handle),
            };

            fclose($handle);

            // Upload ke S3
            Storage::disk('s3')->put($path, fopen($temp, 'r'));

        } finally {
            if (file_exists($temp)) {
                unlink($temp);
            }
        }

        // Notifikasi admin bahwa export sudah siap
        $user = \App\Modules\Auth\Models\User::find($this->requestedBy);
        if ($user) {
            $user->notify(new \App\Notifications\ExportReadyNotification(
                $this->exportId,
                $this->type
            ));
        }
    }

    private function writeRevenueCsv($handle): void
    {
        $dateFrom = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo   = $this->filters['date_to']   ?? now()->toDateString();

        fputcsv($handle, ['Order Number', 'Date', 'User', 'Type', 'Subtotal',
            'Discount', 'Shipping', 'Total', 'Status', 'Payment']);

        DB::table('orders as o')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('payments as p', 'p.order_id', '=', 'o.id')
            ->whereNull('o.deleted_at')
            ->whereBetween(DB::raw('o.created_at::date'), [$dateFrom, $dateTo])
            ->select([
                'o.order_number', 'o.created_at', 'u.name', 'u.user_type',
                'o.subtotal',
                DB::raw('(o.discount_amount + o.coupon_discount + o.points_discount) AS discount'),
                'o.shipping_fee', 'o.total', 'o.status', 'p.method',
            ])
            ->orderBy('o.created_at')
            ->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, (array) $row);
                }
            });
    }

    private function writeOrdersCsv($handle): void
    {
        fputcsv($handle, ['Order Number', 'Date', 'User', 'Items', 'Total', 'Status', 'Courier', 'Tracking']);

        DB::table('orders as o')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->whereNull('o.deleted_at')
            ->when($this->filters['status'] ?? null, fn($q, $v) => $q->where('o.status', $v))
            ->selectRaw('o.order_number, o.created_at, u.name, o.total, o.status, o.shipping_courier, o.tracking_number,
                         (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count')
            ->orderByDesc('o.created_at')
            ->chunk(500, fn($rows) => collect($rows)->each(fn($r) => fputcsv($handle, (array) $r)));
    }

    private function writeProductsCsv($handle): void
    {
        fputcsv($handle, ['SKU', 'Product', 'Variant', 'Category', 'Price', 'Stock', 'Total Sold']);

        DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->select(['pv.sku', 'p.name', 'pv.name as variant', 'c.name as category',
                'pv.price', 'pv.stock', 'p.total_sold'])
            ->orderBy('p.name')
            ->chunk(500, fn($rows) => collect($rows)->each(fn($r) => fputcsv($handle, (array) $r)));
    }

    private function writeUsersCsv($handle): void
    {
        fputcsv($handle, ['Name', 'Email', 'Phone', 'Type', 'Status', 'Registered', 'Orders', 'Total Spent']);

        DB::table('users as u')
            ->leftJoin('orders as o', fn($j) =>
            $j->on('o.user_id', '=', 'u.id')->whereNull('o.deleted_at')->where('o.status', 'completed'))
            ->whereNull('u.deleted_at')
            ->selectRaw('u.name, u.email, u.phone, u.user_type, u.status, u.created_at,
                         COUNT(o.id) AS orders, COALESCE(SUM(o.total), 0) AS spent')
            ->groupBy('u.id', 'u.name', 'u.email', 'u.phone', 'u.user_type', 'u.status', 'u.created_at')
            ->orderByDesc('spent')
            ->chunk(500, fn($rows) => collect($rows)->each(fn($r) => fputcsv($handle, (array) $r)));
    }

    public function failed(\Throwable $e): void
    {
        \Log::error('Admin export failed', [
            'export_id' => $this->exportId,
            'type'      => $this->type,
            'error'     => $e->getMessage(),
        ]);
    }
}
