<?php

namespace App\Modules\Order\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Events\{OrderCancelled, OrderStatusUpdated};
use App\Modules\Order\Models\Order;
use App\Modules\Order\Repositories\Contracts\OrderRepositoryInterface;
use App\Modules\Product\Models\ProductVariant;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RewardService            $rewardService,
    ) {}

    public function getOrdersForUser(string $userId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->orderRepository->paginateForUser($userId, $filters, $perPage);
    }

    public function getOrderDetail(string $orderNumber, string $userId): Order
    {
        $order = $this->orderRepository->findByOrderNumber($orderNumber);

        if (! $order || $order->user_id !== $userId) {
            abort(404, 'Order tidak ditemukan.');
        }

        return $order;
    }

    public function cancelByUser(string $orderNumber, string $userId, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($orderNumber, $userId, $reason) {
            $order = $this->getOrderDetail($orderNumber, $userId);

            if (! $order->canBeCancelledByUser()) {
                throw ValidationException::withMessages([
                    'order' => ["Order dengan status {$order->status->label()} tidak bisa dibatalkan."],
                ]);
            }

            // Kembalikan stok
            $this->restoreStock($order);

            // Kembalikan reward points yang digunakan
            if ($order->points_used > 0) {
                $this->rewardService->refundPoints(
                    userId  : $userId,
                    points  : $order->points_used,
                    orderId : $order->id,
                );
            }

            $order->transitionTo(OrderStatus::Cancelled, $reason ?? 'Dibatalkan oleh pelanggan.', $userId);

            event(new OrderCancelled($order));

            return $order->fresh(['items', 'statusHistories']);
        });
    }

    public function updateStatusByAdmin(
        string      $orderId,
        OrderStatus $newStatus,
        string      $actorId,
        ?string     $notes          = null,
        ?string     $trackingNumber = null,
    ): Order {
        return DB::transaction(function () use ($orderId, $newStatus, $actorId, $notes, $trackingNumber) {
            $order = $this->orderRepository->updateStatus($orderId, $newStatus, $notes, $actorId);

            if ($trackingNumber) {
                $order->update(['tracking_number' => $trackingNumber]);
            }

            // Credit reward points saat order selesai
            if ($newStatus === OrderStatus::Completed) {
                $this->rewardService->earnFromOrder($order);
            }

            // Kembalikan stok jika admin cancel
            if ($newStatus === OrderStatus::Cancelled) {
                $this->restoreStock($order);
            }

            event(new OrderStatusUpdated($order, $newStatus));

            return $order;
        });
    }

    private function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            ProductVariant::where('id', $item->product_variant_id)
                ->increment('stock', $item->quantity);
        }
    }
}
