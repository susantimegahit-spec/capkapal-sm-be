<?php

namespace App\Modules\Order\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Requests\UpdateOrderStatusRequest;
use App\Modules\Order\Resources\OrderResource;
use App\Modules\Order\Services\OrderService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly OrderService $orderService) {}

    // GET /api/v1/orders
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'   => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $orders = $this->orderService->getOrdersForUser(
            $request->user()->id,
            $request->only('status'),
            $request->per_page ?? 10,
        );

        return $this->success(
            OrderResource::collection($orders)->response()->getData(true)
        );
    }

    // GET /api/v1/orders/{orderNumber}
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = $this->orderService->getOrderDetail($orderNumber, $request->user()->id);

        return $this->success(new OrderResource($order));
    }

    // POST /api/v1/orders/{orderNumber}/cancel
    public function cancel(Request $request, string $orderNumber): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $order = $this->orderService->cancelByUser(
            $orderNumber,
            $request->user()->id,
            $request->reason,
        );

        return $this->success(new OrderResource($order), 'Order berhasil dibatalkan.');
    }

    // PATCH /api/v1/admin/orders/{orderId}/status
    public function updateStatus(UpdateOrderStatusRequest $request, string $orderId): JsonResponse
    {
        $order = $this->orderService->updateStatusByAdmin(
            orderId        : $orderId,
            newStatus      : OrderStatus::from($request->status),
            actorId        : $request->user()->id,
            notes          : $request->notes,
            trackingNumber : $request->tracking_number,
        );

        return $this->success(new OrderResource($order), 'Status order diperbarui.');
    }
}
