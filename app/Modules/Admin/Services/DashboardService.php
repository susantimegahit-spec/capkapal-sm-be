<?php

namespace App\Modules\Admin\Services;

use Illuminate\Support\Facades\{Cache, DB};

class DashboardService
{
    /**
     * KPI cards — di-cache 5 menit.
     * Query diarahkan ke read replica via DB::connection('pgsql_read').
     */
    public function getKpis(string $period = 'today'): array
    {
        $cacheKey = "admin:dashboard:kpis:{$period}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($period) {
            [$startDate, $prevStart, $prevEnd] = $this->resolvePeriod($period);

            $db = DB::connection('pgsql');

            // ── Revenue ───────────────────────────────────────────────────────
            $revenue = $db->table('orders')
                ->where('status', 'completed')
                ->whereBetween('created_at', [$startDate, now()])
                ->whereNull('deleted_at')
                ->selectRaw('
                    COALESCE(SUM(total), 0)         AS current,
                    COUNT(*)                         AS order_count
                ')
                ->first();

            $prevRevenue = $db->table('orders')
                ->where('status', 'completed')
                ->whereBetween('created_at', [$prevStart, $prevEnd])
                ->whereNull('deleted_at')
                ->sum('total');

            // ── New Users ─────────────────────────────────────────────────────
            $newUsers = $db->table('users')
                ->whereBetween('created_at', [$startDate, now()])
                ->whereNull('deleted_at')
                ->count();

            $prevNewUsers = $db->table('users')
                ->whereBetween('created_at', [$prevStart, $prevEnd])
                ->whereNull('deleted_at')
                ->count();

            // ── Orders ────────────────────────────────────────────────────────
            $orders = $db->table('orders')
                ->whereBetween('created_at', [$startDate, now()])
                ->whereNull('deleted_at')
                ->selectRaw('
                    COUNT(*) FILTER (WHERE status = \'pending\')    AS pending,
                    COUNT(*) FILTER (WHERE status = \'confirmed\')  AS confirmed,
                    COUNT(*) FILTER (WHERE status = \'processing\') AS processing,
                    COUNT(*) FILTER (WHERE status = \'shipped\')    AS shipped,
                    COUNT(*)                                         AS total
                ')
                ->first();

            // ── Inventory alerts ──────────────────────────────────────────────
            $lowStockCount = $db->table('product_variants')
                ->whereRaw('stock <= low_stock_threshold AND stock > 0 AND is_active = true')
                ->count();

            $outOfStockCount = $db->table('product_variants')
                ->where('stock', 0)
                ->where('is_active', true)
                ->count();

            return [
                'revenue' => [
                    'current'    => (float) $revenue->current,
                    'previous'   => (float) $prevRevenue,
                    'growth_pct' => $this->growthPct($revenue->current, $prevRevenue),
                    'order_count'=> (int) $revenue->order_count,
                ],
                'new_users' => [
                    'current'    => $newUsers,
                    'previous'   => $prevNewUsers,
                    'growth_pct' => $this->growthPct($newUsers, $prevNewUsers),
                ],
                'orders' => [
                    'total'      => (int) $orders->total,
                    'pending'    => (int) $orders->pending,
                    'confirmed'  => (int) $orders->confirmed,
                    'processing' => (int) $orders->processing,
                    'shipped'    => (int) $orders->shipped,
                ],
                'inventory' => [
                    'low_stock'    => $lowStockCount,
                    'out_of_stock' => $outOfStockCount,
                ],
                'period'     => $period,
                'updated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Data chart revenue — per hari/minggu/bulan.
     * Di-cache 15 menit.
     */
    public function getRevenueChart(string $groupBy = 'day', int $limit = 30): array
    {
        $cacheKey = "admin:dashboard:revenue_chart:{$groupBy}:{$limit}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($groupBy, $limit) {
            $trunc = match ($groupBy) {
                'week'  => "date_trunc('week', created_at)",
                'month' => "date_trunc('month', created_at)",
                default => "date_trunc('day', created_at)",
            };

            $rows = DB::connection('pgsql')
                ->table('orders')
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->where('created_at', '>=', now()->subDays($limit))
                ->selectRaw("
                    {$trunc}             AS period,
                    COALESCE(SUM(total), 0) AS revenue,
                    COUNT(*)                AS orders
                ")
                ->groupByRaw("1")
                ->orderByRaw("1 ASC")
                ->get();

            return $rows->map(fn($r) => [
                'period'  => $r->period,
                'revenue' => (float) $r->revenue,
                'orders'  => (int) $r->orders,
            ])->all();
        });
    }

    /**
     * Top produk terlaris — di-cache 30 menit.
     */
    public function getTopProducts(int $limit = 10): array
    {
        return Cache::remember("admin:dashboard:top_products:{$limit}", now()->addMinutes(30), function () use ($limit) {
            return DB::connection('pgsql')
                ->table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->join('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->where('o.status', 'completed')
                ->whereNull('o.deleted_at')
                ->where('o.created_at', '>=', now()->subDays(30))
                ->selectRaw('
                    p.id,
                    p.name,
                    p.slug,
                    SUM(oi.quantity)  AS total_qty,
                    SUM(oi.subtotal)  AS total_revenue
                ')
                ->groupBy('p.id', 'p.name', 'p.slug')
                ->orderByDesc('total_revenue')
                ->limit($limit)
                ->get()
                ->map(fn($r) => [
                    'id'            => $r->id,
                    'name'          => $r->name,
                    'slug'          => $r->slug,
                    'total_qty'     => (int) $r->total_qty,
                    'total_revenue' => (float) $r->total_revenue,
                ])
                ->all();
        });
    }

    /**
     * Distribusi user per tipe — di-cache 1 jam.
     */
    public function getUserDistribution(): array
    {
        return Cache::remember('admin:dashboard:user_dist', now()->addHour(), function () {
            return DB::connection('pgsql')
                ->table('users')
                ->whereNull('deleted_at')
                ->selectRaw("
                    user_type,
                    COUNT(*)                                     AS total,
                    COUNT(*) FILTER (WHERE status = 'active')   AS active,
                    COUNT(*) FILTER (WHERE created_at >= NOW() - INTERVAL '30 days') AS new_this_month
                ")
                ->groupBy('user_type')
                ->get()
                ->keyBy('user_type')
                ->toArray();
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function resolvePeriod(string $period): array
    {
        return match ($period) {
            'today'   => [now()->startOfDay(),   now()->subDay()->startOfDay(),  now()->subDay()->endOfDay()],
            'week'    => [now()->startOfWeek(),  now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'month'   => [now()->startOfMonth(), now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'year'    => [now()->startOfYear(),  now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            default   => [now()->startOfDay(),   now()->subDay()->startOfDay(),  now()->subDay()->endOfDay()],
        };
    }

    private function growthPct(float $current, float $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
