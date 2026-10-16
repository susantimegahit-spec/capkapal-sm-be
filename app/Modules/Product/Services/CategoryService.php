<?php

namespace App\Modules\Product\Services;

use App\Modules\Product\Models\Category;
use App\Modules\Product\Repositories\Eloquent\CategoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Cache, Storage};

class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    public function getTree(): Collection
    {
        return $this->categoryRepository->getTree();
    }

    public function findBySlug(string $slug): Category
    {
        $category = $this->categoryRepository->findBySlug($slug);

        abort_if(! $category, 404, 'Kategori tidak ditemukan.');

        return $category;
    }

    public function create(array $data, ?UploadedFile $image = null): Category
    {
        if ($image) {
            $data['image'] = $image->store('categories', 's3');
        }

        $category = $this->categoryRepository->create($data);

        return $category;
    }

    public function update(string $id, array $data, ?UploadedFile $image = null): Category
    {
        if ($image) {
            $old = $this->categoryRepository->findOrFail($id);
            if ($old->image) {
                Storage::disk('s3')->delete($old->image);
            }
            $data['image'] = $image->store('categories', 's3');
        }

        $category = $this->categoryRepository->update($id, $data);

        return $category;
    }
}
