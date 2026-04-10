<?php

namespace App\Modules\Order\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Order\DataTransferObjects\CheckoutData;
use App\Modules\Order\Requests\CheckoutRequest;
use App\Modules\Order\Resources\OrderResource;
use App\Modules\Order\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CheckoutController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly CheckoutService $checkoutService) {}

    // POST /api/v1/checkout
    public function process(CheckoutRequest $request): JsonResponse
    {
        $data = CheckoutData::from($request->validated());

        ['order' => $order, 'payment' => $payment] = $this->checkoutService->process(
            $request->user()->id,
            $data,
        );

        return $this->created([
            'order_number' => $order->order_number,
            'total'        => $order->total,
            'order'        => new OrderResource($order),
            'payment'      => $payment,
        ], 'Order berhasil dibuat.');
    }
}
