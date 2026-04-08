<?php

namespace App\Modules\Product\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Product\Resources\CategoryResource;
use App\Modules\Product\Services\CategoryService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class CategoryController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CategoryService $categoryService
    ) {}

    // GET /api/v1/categories
    public function index(): JsonResponse
    {
        return $this->success(
            CategoryResource::collection($this->categoryService->getTree())
        );
    }

    // GET /api/v1/categories/{slug}
    public function show(string $slug): JsonResponse
    {
        return $this->success(
            new CategoryResource($this->categoryService->findBySlug($slug))
        );
    }

    // POST /api/v1/admin/categories
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'parent_id'   => ['nullable', 'uuid', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['boolean'],
        ]);

        $category = $this->categoryService->create(
            $request->except('image'),
            $request->file('image'),
        );

        return $this->created(new CategoryResource($category), 'Kategori berhasil dibuat.');
    }

    // PUT /api/v1/admin/categories/{id}
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name'        => ['sometimes', 'string', 'max:100'],
            'parent_id'   => ['nullable', 'uuid', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['boolean'],
        ]);

        $category = $this->categoryService->update($id, $request->except('image'), $request->file('image'));

        return $this->success(new CategoryResource($category), 'Kategori berhasil diperbarui.');
    }
}
