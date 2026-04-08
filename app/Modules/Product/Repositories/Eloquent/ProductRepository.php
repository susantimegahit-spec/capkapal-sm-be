<?php

namespace App\Modules\Product\Repositories\Eloquent;

use App\Modules\Product\Enums\ProductStatus;
use App\Modules\Product\Models\{Product, ProductVariant};
use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\{Collection, Model};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(protected Product $model) {}

    // ── Public listing (storefront) ───────────────────────────────────────────
    public function listPublic(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['primaryImage', 'activeVariants', 'category'])
            ->active()
            ->when($filters['category_id'] ?? null,
                fn($q, $v) => $q->where('category_id', $v))
            ->when($filters['is_featured'] ?? null,
                fn($q) => $q->featured())
            ->when($filters['min_price'] ?? null,
                fn($q, $v) => $q->whereHas('activeVariants', fn($vq) => $vq->where('price', '>=', $v)))
            ->when($filters['max_price'] ?? null,
                fn($q, $v) => $q->whereHas('activeVariants', fn($vq) => $vq->where('price', '<=', $v)))
            ->when($filters['in_stock'] ?? null,
                fn($q) => $q->whereHas('activeVariants', fn($vq) => $vq->where('stock', '>', 0)))
            ->orderBy(...$this->resolveSort($filters['sort'] ?? 'latest'))
            ->paginate($perPage);
    }

    // ── Admin listing (semua status) ──────────────────────────────────────────
    public function listAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['primaryImage', 'category'])
            ->withTrashed()
            ->when($filters['status'] ?? null,
                fn($q, $v) => $q->where('status', $v))
            ->when($filters['category_id'] ?? null,
                fn($q, $v) => $q->where('category_id', $v))
            ->when($filters['search'] ?? null,
                fn($q, $v) => $q->where(fn($s) =>
                $s->where('name', 'ilike', "%{$v}%")
                    ->orWhere('sku', 'ilike', "%{$v}%")
                ))
            ->orderBy(...$this->resolveSort($filters['sort'] ?? 'latest'))
            ->paginate($perPage);
    }

    public function getFeatured(int $limit = 8): Collection
    {
        return $this->model
            ->with(['primaryImage', 'activeVariants'])
            ->active()
            ->featured()
            ->limit($limit)
            ->get();
    }

    public function all(array $filters = []): Collection
    {
        return $this->model->active()->get();
    }

    public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        return $this->listPublic($filters, $perPage);
    }

    public function findById(string|int $id): ?Product
    {
        return $this->model
            ->with(['category', 'activeVariants', 'images'])
            ->find($id);
    }

    public function findOrFail(string|int $id): Product
    {
        return $this->model
            ->with(['category', 'activeVariants', 'images'])
            ->findOrFail($id);
    }

    public function findBySlug(string $slug): ?Product
    {
        return $this->model
            ->with(['category', 'activeVariants', 'images', 'reviews.user'])
            ->where('slug', $slug)
            ->active()
            ->first();
    }

    public function create(array $data): Product
    {
        return $this->model->create($data);
    }

    public function update(string|int $id, array $data): Product
    {
        $product = $this->findOrFail($id);
        $product->update($data);
        return $product->fresh(['category', 'activeVariants', 'images']);
    }

    public function delete(string|int $id): bool
    {
        return $this->findOrFail($id)->delete(); // soft delete
    }

    public function incrementSoldCount(string $productId, int $qty): void
    {
        $this->model->where('id', $productId)->increment('total_sold', $qty);
    }

    public function decrementStock(string $variantId, int $qty): bool
    {
        // Atomic: gunakan lock untuk hindari race condition
        return DB::transaction(function () use ($variantId, $qty) {
            $variant = ProductVariant::lockForUpdate()->findOrFail($variantId);

            if ($variant->stock < $qty) {
                return false; // stok tidak cukup
            }

            $variant->decrement('stock', $qty);
            return true;
        });
    }

    public function recalculateRating(string $productId): void
    {
        $product = $this->model->withCount(['reviews as review_count'])
            ->withAvg('reviews as rating_avg', 'rating')
            ->findOrFail($productId);

        $product->update([
            'rating_avg'   => round($product->rating_avg ?? 0, 2),
            'review_count' => $product->review_count,
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function resolveSort(string $sort): array
    {
        return match ($sort) {
            'price_asc'   => ['base_price', 'asc'],
            'price_desc'  => ['base_price', 'desc'],
            'rating'      => ['rating_avg', 'desc'],
            'popular'     => ['total_sold', 'desc'],
            'name_asc'    => ['name', 'asc'],
            default       => ['created_at', 'desc'],  // 'latest'
        };
    }
}
