<?php

namespace App\Modules\Product\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Product\Requests\StoreReviewRequest;
use App\Modules\Product\Resources\ProductReviewResource;
use App\Modules\Product\Services\ProductService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class ReviewController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ProductService $productService) {}

    // GET /api/v1/products/{slug}/reviews
    public function index(Request $request, string $slug): JsonResponse
    {
        $product = $this->productService->findBySlug($slug);

        $reviews = $product->reviews()
            ->with('user')
            ->when($request->rating, fn($q, $v) => $q->where('rating', $v))
            ->latest()
            ->paginate($request->per_page ?? 10);

        return $this->success(
            ProductReviewResource::collection($reviews)->response()->getData(true)
        );
    }

    // POST /api/v1/products/{slug}/reviews
    public function store(StoreReviewRequest $request, string $slug): JsonResponse
    {
        $product = $this->productService->findBySlug($slug);

        $review = $this->productService->submitReview(
            productId: $product->id,
            userId   : $request->user()->id,
            data     : $request->validated(),
        );

        return $this->created(
            new ProductReviewResource($review),
            'Ulasan berhasil dikirim.'
        );
    }
}
