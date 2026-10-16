<?php
// app/Modules/Order/Listeners/SendOrderConfirmationEmail.php
namespace App\Modules\Order\Listeners;

use App\Modules\Order\Events\OrderPlaced;
use App\Mail\OrderConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail implements ShouldQueue
{
    public string $queue = 'high';

    public function handle(OrderPlaced $event): void
    {
        Mail::to($event->order->user->email)
            ->send(new OrderConfirmationMail($event->order));
    }
}
