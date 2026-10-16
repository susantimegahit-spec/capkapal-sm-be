<?php

namespace App\Modules\Admin\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Admin\Services\AuditService;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Resources\OrderResource;
use App\Modules\Order\Services\OrderService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rules\Enum;

class OrderManagementController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly OrderService  $orderService,
        private readonly AuditService  $auditService,
    ) {}

    // GET /api/v1/admin/orders
    // GET /api/v1/admin/orders
    public function index(Request $request): JsonResponse
    {
        // 1. Tampung kembalian dari validate() ke dalam variabel
        $validatedData = $request->validate([
            'status'    => ['nullable', new Enum(OrderStatus::class)],
            'search'    => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // 2. Masukkan variabel tersebut ke dalam fungsi paginateAdmin
        $orders = $this->orderService->orderRepository->paginateAdmin(
            $validatedData,
            $request->per_page ?? 20,
        );

        return $this->success(
            OrderResource::collection($orders)->response()->getData(true)
        );
    }

    // GET /api/v1/admin/orders/{orderNumber}
    public function show(string $orderNumber): JsonResponse
    {
        $order = Order::with([
            'user.profile',
            'items.variant.product',
            'payment.logs',
            'shippingAddress',
            'statusHistories',
        ])->where('order_number', $orderNumber)->firstOrFail();

        return $this->success(new OrderResource($order));
    }

    // PATCH /api/v1/admin/orders/{orderId}/status
    public function updateStatus(Request $request, string $orderId): JsonResponse
    {
        $request->validate([
            'status'          => ['required', new Enum(OrderStatus::class)],
            'notes'           => ['nullable', 'string', 'max:500'],
            'tracking_number' => ['nullable', 'string', 'max:50'],
        ]);

        $order = $this->orderService->updateStatusByAdmin(
            orderId: $orderId,
            newStatus: OrderStatus::from($request->status),
            actorId: $request->user()->id,
            notes: $request->notes,
            trackingNumber: $request->tracking_number,
        );

        $this->auditService->logAdminAction(
            action: 'order_status_updated',
            subject: $order,
            properties: ['new_status' => $request->status, 'notes' => $request->notes],
            causerId: $request->user()->id,
        );

        return $this->success(new OrderResource($order), 'Status order diperbarui.');
    }

    // POST /api/v1/admin/orders/{orderId}/notes
    public function addNote(Request $request, string $orderId): JsonResponse
    {
        $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        $order = Order::findOrFail($orderId);
        $order->update(['admin_notes' => $request->notes]);

        $this->auditService->logAdminAction('order_note_added', $order, [], $request->user()->id);

        return $this->success(null, 'Catatan berhasil ditambahkan.');
    }
}
