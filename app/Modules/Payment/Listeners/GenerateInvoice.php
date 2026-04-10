<?php
// app/Modules/Payment/Listeners/GenerateInvoice.php
namespace App\Modules\Payment\Listeners;

use App\Jobs\GenerateOrderInvoice;
use App\Modules\Payment\Events\PaymentSucceeded;
use Illuminate\Contracts\Queue\ShouldQueue;

class GenerateInvoice implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(PaymentSucceeded $event): void
    {
        GenerateOrderInvoice::dispatch($event->payment->order_id)->onQueue('default');
    }
}
