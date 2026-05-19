<?php

namespace App\Modules\Payment\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Payment\Services\PaymentService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly PaymentService $paymentService) {}

    // GET /api/v1/payments/status/{orderId}
    public function status(Request $request, string $orderId): JsonResponse
    {
        $result = $this->paymentService->checkStatus($orderId, $request->user()->id);

        return $this->success($result);
    }

    // POST /api/v1/payments/{orderNumber}/retry
    public function retry(Request $request, string $orderNumber): JsonResponse
    {
        $request->validate([
            'payment_method'   => ['required', 'string', 'in:virtual_account,ewallet,qris,retail_outlet'],
            'payment_provider' => ['nullable', 'string', 'max:30'],
        ]);

        $instructions = $this->paymentService->retryPayment(
            orderNumber: $orderNumber,
            userId     : $request->user()->id,
            method     : $request->payment_method,
            provider   : $request->payment_provider,
        );

        return $this->success($instructions, 'Instruksi pembayaran baru berhasil dibuat.');
    }

    // POST /api/v1/admin/payments/sync-all
    public function syncAll(): JsonResponse
    {
        // Jika perlu, tambahkan gate authorization:
        // $this->authorize('sync-payments');

        $result = $this->paymentService->syncAllPendingPayments();

        return $this->success(
            $result,
            "Sinkronisasi selesai. {$result['synced']} terupdate, {$result['failed']} gagal."
        );
    }
}
