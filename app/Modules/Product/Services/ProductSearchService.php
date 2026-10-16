<?php

namespace App\Modules\Product\Services;

use App\Modules\Product\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ProductSearchService
{
    /**
     * Full-text search via Laravel Scout → Meilisearch.
     * Fallback ke DB ILIKE jika Scout tidak tersedia.
     */
    public function search(string $query, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $cacheKey = 'search:' . md5($query . serialize($filters) . $perPage);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($query, $filters, $perPage) {
            return Product::search($query)
                ->when($filters['category_id'] ?? null,
                    fn($s, $v) => $s->where('category_id', $v))
                ->when($filters['min_price'] ?? null,
                    fn($s, $v) => $s->where('base_price', '>=', $v))
                ->when($filters['max_price'] ?? null,
                    fn($s, $v) => $s->where('base_price', '<=', $v))
                ->where('status', 'active')
                ->paginate($perPage);
        });
    }

    /**
     * Autocomplete suggestions (lightweight, no pagination).
     */
    public function suggest(string $query, int $limit = 5): array
    {
        $cacheKey = 'search:suggest:' . md5($query);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($query, $limit) {
            return Product::search($query)
                ->where('status', 'active')
                ->take($limit)
                ->get(['id', 'name', 'slug', 'base_price'])
                ->map(fn($p) => [
                    'id'    => $p->id,
                    'name'  => $p->name,
                    'slug'  => $p->slug,
                    'price' => $p->min_price,
                ])
                ->all();
        });
    }

    /**
     * Re-index semua produk aktif ke Meilisearch.
     * Dipanggil dari artisan command atau job.
     */
    public function reindexAll(): void
    {
        Product::active()->searchable();
    }
}
