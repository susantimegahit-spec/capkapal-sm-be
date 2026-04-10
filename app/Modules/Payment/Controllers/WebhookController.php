<?php

namespace App\Modules\Payment\Controllers;

use App\Modules\Payment\Services\WebhookService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    public function __construct(private readonly WebhookService $webhookService) {}

    /**
     * POST /api/v1/webhooks/xendit
     *
     * Endpoint ini menerima semua jenis webhook dari Xendit:
     * - Virtual Account payment
     * - eWallet charge completion
     * - QRIS payment
     * - Retail outlet payment
     *
     * Verifikasi signature sudah dilakukan di VerifyXenditWebhook middleware.
     * Response harus cepat (< 30s) — logika berat di-dispatch ke queue.
     */
    public function handleXendit(Request $request): JsonResponse
    {
        $this->webhookService->handle($request);

        // Xendit mengharapkan 200 OK secepat mungkin
        return response()->json(['status' => 'received'], 200);
    }
}
