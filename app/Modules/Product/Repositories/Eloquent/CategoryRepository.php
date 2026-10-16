<?php

namespace App\Modules\Product\Repositories\Eloquent;

use App\Modules\Product\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository
{
    public function __construct(protected Category $model) {}

    public function getTree(): Collection
    {
        return $this->model
            ->with('allChildren')
            ->active()
            ->roots()
            ->orderBy('sort_order')
            ->get();
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->model
            ->with(['children' => fn($q) => $q->active()->orderBy('sort_order')])
            ->active()
            ->where('slug', $slug)
            ->first();
    }

    public function findOrFail(string $id): Category
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): Category
    {
        return $this->model->create($data);
    }

    public function update(string $id, array $data): Category
    {
        $category = $this->findOrFail($id);
        $category->update($data);
        return $category->fresh();
    }
}
