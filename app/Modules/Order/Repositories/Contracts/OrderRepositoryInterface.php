<?php

namespace App\Modules\Order\Repositories\Contracts;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function findByOrderNumber(string $orderNumber): ?Order;

    public function findByIdForUser(string $orderId, string $userId): ?Order;

    public function paginateForUser(string $userId, array $filters, int $perPage): LengthAwarePaginator;

    public function paginateAdmin(array $filters, int $perPage): LengthAwarePaginator;

    public function create(array $data): Order;

    public function updateStatus(string $orderId, OrderStatus $status, ?string $notes, ?string $actorId): Order;

    public function generateOrderNumber(): string;
}
