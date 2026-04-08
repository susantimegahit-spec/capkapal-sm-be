<?php

namespace App\Modules\Product\Repositories\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Modules\Product\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug): ?Product;

    public function listPublic(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function listAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function getFeatured(int $limit = 8): \Illuminate\Database\Eloquent\Collection;

    public function incrementSoldCount(string $productId, int $qty): void;

    public function decrementStock(string $variantId, int $qty): bool;

    public function recalculateRating(string $productId): void;
}
