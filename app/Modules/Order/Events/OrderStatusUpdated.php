<?php
// app/Modules/Order/Events/OrderPlaced.php
namespace App\Modules\Order\Events;

use App\Modules\Order\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated
{
    use Dispatchable, SerializesModels;
    public function __construct(public readonly Order $order, public readonly string $newStatus) {}
}

// OrderCancelled.php — struktur sama
// OrderStatusUpdated.php — tambah parameter OrderStatus $newStatus
