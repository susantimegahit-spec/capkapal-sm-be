<?php

namespace App\Modules\Order\Repositories\Eloquent;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(protected Order $model) {}

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return $this->model
            ->with(['items.variant.product', 'payment', 'shippingAddress', 'statusHistories'])
            ->where('order_number', $orderNumber)
            ->first();
    }

    public function findByIdForUser(string $orderId, string $userId): ?Order
    {
        return $this->model
            ->with(['items.variant.product', 'payment', 'shippingAddress', 'statusHistories'])
            ->where('id', $orderId)
            ->where('user_id', $userId)
            ->first();
    }

    public function paginateForUser(string $userId, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->model
            ->with(['items.variant', 'payment', 'shippingAddress'])
            ->forUser($userId)
            ->when($filters['status'] ?? null,
                fn($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate($perPage);
    }

    public function paginateAdmin(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['user', 'items', 'payment'])
            ->when($filters['status'] ?? null,
                fn($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null,
                fn($q, $v) => $q->where(fn($s) =>
                $s->where('order_number', 'ilike', "%{$v}%")
                    ->orWhereHas('user', fn($u) => $u->where('name', 'ilike', "%{$v}%"))
                ))
            ->when($filters['date_from'] ?? null,
                fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null,
                fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Order
    {
        return $this->model->create($data);
    }

    public function updateStatus(string $orderId, OrderStatus $status, ?string $notes, ?string $actorId): Order
    {
        $order = $this->model->findOrFail($orderId);
        $order->transitionTo($status, $notes, $actorId);
        return $order->fresh(['items', 'payment', 'shippingAddress']);
    }

    public function generateOrderNumber(): string
    {
        // Format: GCK-YYYYMMDD-XXXXX (atomic, padded)
        $prefix = 'GCK-' . now()->format('Ymd') . '-';

        $last = $this->model
            ->where('order_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('order_number')
            ->value('order_number');

        $sequence = $last
            ? (int) substr($last, -5) + 1
            : 1;

        return $prefix . str_pad($sequence, 5, '0', STR_PAD_LEFT);
    }
}
