<?php

namespace App\Modules\Payment\Controllers;

use App\Modules\Payment\Services\WebhookService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    public function __construct(private readonly WebhookService $webhookService) {}

    /**
     * POST /api/v1/webhooks/midtrans
     *
     * Endpoint ini menerima semua jenis notification dari Midtrans:
     * - Virtual Account payment (bank_transfer)
     * - eWallet charge (gopay, shopeepay)
     * - QRIS payment
     * - Retail outlet / CStore payment
     *
     * Verifikasi signature sudah dilakukan di VerifyMidtransNotification middleware.
     * Response harus cepat (< 30s) — logika berat di-dispatch ke queue.
     */
    public function handleMidtrans(Request $request): JsonResponse
    {
        $this->webhookService->handle($request);

        // Midtrans mengharapkan 200 OK secepat mungkin
        return response()->json(['status' => 'received'], 200);
    }
}
