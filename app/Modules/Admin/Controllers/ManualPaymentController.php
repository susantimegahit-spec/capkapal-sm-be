<?php

namespace App\Modules\Admin\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Admin\Services\AuditService;
use App\Modules\Payment\Services\ManualPaymentService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class ManualPaymentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ManualPaymentService $manualPaymentService,
        private readonly AuditService         $auditService,
    ) {}

    /**
     * GET /api/v1/admin/manual-payments
     *
     * List semua pembayaran manual QRIS yang menunggu verifikasi admin.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage  = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']])['per_page'] ?? 20;
        $payments = $this->manualPaymentService->getPendingVerifications($perPage);

        return $this->success($payments);
    }

    /**
     * POST /api/v1/admin/manual-payments/{paymentId}/approve
     *
     * Admin menyetujui bukti pembayaran manual.
     */
    public function approve(Request $request, string $paymentId): JsonResponse
    {
        $payment = $this->manualPaymentService->approvePayment(
            paymentId: $paymentId,
            adminId  : $request->user()->id,
        );

        $this->auditService->logAdminAction(
            action    : 'manual_payment_approved',
            subject   : $payment,
            properties: ['order_number' => $payment->order->order_number],
            causerId  : $request->user()->id,
        );

        return $this->success(
            $payment->load('order'),
            'Pembayaran berhasil diverifikasi. Order status berubah ke Confirmed.'
        );
    }

    /**
     * POST /api/v1/admin/manual-payments/{paymentId}/reject
     *
     * Admin menolak bukti pembayaran manual dengan alasan.
     */
    public function reject(Request $request, string $paymentId): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $payment = $this->manualPaymentService->rejectPayment(
            paymentId: $paymentId,
            adminId  : $request->user()->id,
            reason   : $request->reason,
        );

        $this->auditService->logAdminAction(
            action    : 'manual_payment_rejected',
            subject   : $payment,
            properties: [
                'order_number' => $payment->order->order_number,
                'reason'       => $request->reason,
            ],
            causerId  : $request->user()->id,
        );

        return $this->success(
            $payment->load('order'),
            'Bukti pembayaran ditolak. Customer dapat mengunggah bukti baru (24 jam).'
        );
    }

    /**
     * POST /api/v1/admin/manual-payments/expire-overdue
     *
     * Expire semua pembayaran manual QRIS yang sudah melewati deadline 24 jam.
     * Mengembalikan stok dan poin yang sudah dideduct.
     */
    public function expireOverdue(Request $request): JsonResponse
    {
        $result = $this->manualPaymentService->expireOverduePayments();

        $this->auditService->logAdminAction(
            action    : 'manual_payments_expired',
            subject   : null,
            properties: $result,
            causerId  : $request->user()->id,
        );

        return $this->success(
            $result,
            "Expire selesai. {$result['expired']} pembayaran di-expire, {$result['failed']} gagal."
        );
    }
}
