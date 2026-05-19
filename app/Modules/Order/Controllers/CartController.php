<?php

namespace App\Modules\Order\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Order\Requests\AddCartItemRequest;
use App\Modules\Order\Resources\CartResource;
use App\Modules\Order\Services\CartService;
use App\Modules\Order\Services\ShippingService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly CartService $cartService) {}

    // GET /api/v1/cart
    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->getCart($request->user()->id);

        return $this->success(new CartResource($cart));
    }

    // POST /api/v1/cart/items
    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $cart = $this->cartService->addItem(
            userId   : $request->user()->id,
            variantId: $request->product_variant_id,
            quantity : $request->quantity,
            notes    : $request->notes,
            userType : $request->user()->user_type->value,
        );

        return $this->success(new CartResource($cart), 'Item berhasil ditambahkan.');
    }

    // PUT /api/v1/cart/items/{cartItem}
    public function updateItem(Request $request, string $cartItem): JsonResponse
    {
        $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:99']]);

        $cart = $this->cartService->updateItem(
            $request->user()->id,
            $cartItem,
            $request->quantity,
        );

        return $this->success(new CartResource($cart), 'Keranjang diperbarui.');
    }

    // DELETE /api/v1/cart/items/{cartItem}
    public function removeItem(Request $request, string $cartItem): JsonResponse
    {
        $cart = $this->cartService->removeItem($request->user()->id, $cartItem);

        return $this->success(new CartResource($cart), 'Item dihapus.');
    }

    // POST /api/v1/cart/coupon
    public function applyCoupon(Request $request): JsonResponse
    {
        $request->validate(['coupon_code' => ['required', 'string', 'max:50']]);

        $cart = $this->cartService->applyCoupon($request->user()->id, $request->coupon_code);

        return $this->success(new CartResource($cart), 'Kupon berhasil diterapkan.');
    }

    // DELETE /api/v1/cart/coupon
    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->cartService->removeCoupon($request->user()->id);

        return $this->success(new CartResource($cart), 'Kupon dihapus.');
    }

    // GET /api/v1/cart/shipping-options
    public function shippingOptions(Request $request, ShippingService $shippingService): JsonResponse
    {
        $request->validate([
            'city'   => [
                'required',
                'string',
                Rule::in([
                    'Kota Surabaya',
                    'Surabaya',
                    'Kabupaten Gresik',
                    'Gresik',
                    'Kabupaten Sidoarjo',
                    'Sidoarjo'
                ])
            ],
            'weight' => ['nullable', 'numeric'],
        ], [
            'city.in' => 'Maaf, layanan pengiriman kami saat ini hanya menjangkau area Surabaya, Gresik, dan Sidoarjo.',
        ]);

        $options = $shippingService->getAvailableCouriers(
            $request->city,
            $request->weight ?? 500,
        );

        return $this->success($options);
    }
}
