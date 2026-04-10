<?php
// app/Modules/Payment/Listeners/SendPaymentSuccessEmail.php
namespace App\Modules\Payment\Listeners;

use App\Modules\Payment\Events\PaymentSucceeded;
use App\Mail\PaymentSuccessMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendPaymentSuccessEmail implements ShouldQueue
{
    public string $queue = 'high';

    public function handle(PaymentSucceeded $event): void
    {
        Mail::to($event->payment->order->user->email)
            ->send(new PaymentSuccessMail($event->payment->order));
    }
}
