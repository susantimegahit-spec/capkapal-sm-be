<?php

namespace App\Modules\Product\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Product\Requests\{ListProductRequest, StoreProductRequest, UpdateProductRequest};
use App\Modules\Product\Resources\ProductResource;
use App\Modules\Product\Services\{ProductSearchService, ProductService};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class ProductController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ProductService       $productService,
        private readonly ProductSearchService $searchService,
    ) {}

    // GET /api/v1/products
    public function index(ListProductRequest $request): JsonResponse
    {
        $filters = $request->validated();

        // Gunakan Scout search jika ada query string
        if (! empty($filters['search'])) {
            $products = $this->searchService->search(
                query  : $filters['search'],
                filters: $filters,
                perPage: $filters['per_page'] ?? 20,
            );
        } else {
            $products = $this->productService->listPublic(
                filters: $filters,
                perPage: $filters['per_page'] ?? 20,
            );
        }

        return $this->success(
            ProductResource::collection($products)->response()->getData(true)
        );
    }

    // GET /api/v1/products/featured
    public function featured(): JsonResponse
    {
        $products = $this->productService->getFeatured();

        return $this->success(ProductResource::collection($products));
    }

    // GET /api/v1/products/search/suggest?q=garam
    public function suggest(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        $suggestions = $this->searchService->suggest($request->q);

        return $this->success($suggestions);
    }

    // GET /api/v1/products/{slug}
    public function show(string $slug, Request $request): JsonResponse
    {
        $product = $this->productService->findBySlug($slug);

        return $this->success(new ProductResource($product));
    }

    // ── Admin endpoints ───────────────────────────────────────────────────────

    // GET /api/v1/admin/products
    public function adminIndex(ListProductRequest $request): JsonResponse
    {
        $products = $this->productService->listAdmin(
            $request->validated(),
            $request->per_page ?? 20,
        );

        return $this->success(
            ProductResource::collection($products)->response()->getData(true)
        );
    }

    // POST /api/v1/admin/products
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data     = $request->safe()->except(['variants', 'images']);
        $variants = $request->validated('variants', []);
        $images   = collect($request->file('images', []))
            ->map(fn($file) => ['file' => $file])
            ->all();

        $product = $this->productService->create($data, $variants, $images);

        return $this->created(
            new ProductResource($product),
            'Produk berhasil dibuat.'
        );
    }

    // PUT /api/v1/admin/products/{product}
    public function update(UpdateProductRequest $request, string $product): JsonResponse
    {
        $updated = $this->productService->update($product, $request->validated());

        return $this->success(new ProductResource($updated), 'Produk berhasil diperbarui.');
    }

    // DELETE /api/v1/admin/products/{product}
    public function destroy(string $product): JsonResponse
    {
        $this->productService->delete($product);

        return $this->noContent();
    }

    // POST /api/v1/admin/products/{product}/images
    public function uploadImage(Request $request, string $product): JsonResponse
    {
        $request->validate([
            'image'      => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_primary' => ['boolean'],
        ]);

        $productModel = $this->productService->findBySlug($product)
            ?? $this->productService->listAdmin(['search' => $product])->first();

        $image = $this->productService->attachImage(
            product   : $productModel,
            file      : $request->file('image'),
            isPrimary : $request->boolean('is_primary', false),
        );

        return $this->created($image, 'Gambar berhasil diunggah.');
    }

    // DELETE /api/v1/admin/products/images/{image}
    public function deleteImage(string $image): JsonResponse
    {
        $this->productService->deleteImage($image);

        return $this->noContent();
    }

    // PATCH /api/v1/admin/products/{product}/stock
    public function updateStock(Request $request, string $variantId): JsonResponse
    {
        $request->validate([
            'qty'       => ['required', 'integer', 'min:0'],
            'operation' => ['required', 'in:set,increment,decrement'],
        ]);

        $variant = $this->productService->updateStock(
            $variantId,
            $request->qty,
            $request->operation,
        );

        return $this->success($variant, 'Stok berhasil diperbarui.');
    }
}
