<?php

namespace App\Modules\Payment\Http\Middleware;

use App\Modules\Payment\Services\WebhookService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMidtransNotification
{
    public function __construct(private readonly WebhookService $webhookService) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->webhookService->verifySignature($request)) {
            \Log::warning('Invalid Midtrans notification signature', [
                'ip'     => $request->ip(),
                'order_id' => $request->input('order_id', 'unknown'),
            ]);

            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return $next($request);
    }
}
