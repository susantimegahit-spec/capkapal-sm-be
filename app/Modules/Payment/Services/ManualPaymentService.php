<?php

namespace App\Modules\Payment\Services;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Enums\{PaymentMethod, PaymentStatus};
use App\Modules\Payment\Models\Payment;
use App\Modules\Product\Models\ProductVariant;
use App\Modules\Reward\Models\PointTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Log, Storage};
use Illuminate\Validation\ValidationException;

class ManualPaymentService
{
    /**
     * Batas upload ulang bukti pembayaran.
     * Mencegah abuse (spam upload gambar berulang-ulang).
     */
    private const MAX_UPLOAD_ATTEMPTS = 5;

    // ── Customer: Upload Bukti Pembayaran ─────────────────────────────────────

    /**
     * Upload bukti transfer untuk pembayaran manual QRIS.
     * Bisa dipanggil ulang jika payment sebelumnya ditolak (re-upload).
     *
     * Validasi keamanan:
     * - Order milik user yang sedang login
     * - Payment method harus manual_qris
     * - Status harus Waiting atau Rejected
     * - Belum expired (24 jam)
     * - File MIME type harus image
     * - Batas re-upload (mencegah abuse)
     */
    public function uploadProof(string $orderNumber, string $userId, UploadedFile $file): Payment
    {
        // ── Validasi file content-type (double check, pertahanan berlapis) ──
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $detectedMime = $file->getMimeType();

        if (! in_array($detectedMime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'payment_proof' => ['File yang diunggah bukan gambar yang valid.'],
            ]);
        }

        return DB::transaction(function () use ($orderNumber, $userId, $file) {
            $order = Order::where('order_number', $orderNumber)
                ->where('user_id', $userId)
                ->firstOrFail();

            $payment = Payment::where('order_id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            // ── Guard clauses ─────────────────────────────────────────────

            // Hanya untuk pembayaran manual QRIS
            if ($payment->method !== PaymentMethod::ManualQris) {
                throw ValidationException::withMessages([
                    'payment' => ['Upload bukti hanya tersedia untuk pembayaran Manual QRIS.'],
                ]);
            }

            // Status harus Waiting (belum pernah upload) atau Rejected (re-upload)
            if (! in_array($payment->status, [PaymentStatus::Waiting, PaymentStatus::Rejected])) {
                throw ValidationException::withMessages([
                    'payment' => ['Pembayaran tidak dalam status yang memungkinkan upload bukti.'],
                ]);
            }

            // Validasi belum expired
            if ($payment->expired_at && $payment->expired_at->isPast()) {
                throw ValidationException::withMessages([
                    'payment' => ['Batas waktu pembayaran telah lewat (24 jam). Silakan buat pesanan baru.'],
                ]);
            }

            // Cegah abuse: batas upload
            $uploadCount = $order->statusHistories()
                ->where('notes', 'like', 'Bukti pembayaran diunggah%')
                ->count();

            if ($uploadCount >= self::MAX_UPLOAD_ATTEMPTS) {
                throw ValidationException::withMessages([
                    'payment' => ['Batas upload bukti pembayaran tercapai. Silakan hubungi admin.'],
                ]);
            }

            // ── Hapus file bukti lama jika ada (re-upload) ────────────────
            if ($payment->payment_proof_path) {
                Storage::disk('public')->delete($payment->payment_proof_path);
            }

            // ── Simpan file baru ──────────────────────────────────────────
            // Laravel store() menggunakan random hash sebagai filename → aman dari path traversal
            $path = $file->store("payment-proofs/{$order->order_number}", 'public');

            $payment->update([
                'payment_proof_path' => $path,
                'rejection_reason'   => null,  // Reset rejection reason saat re-upload
                'status'             => PaymentStatus::AwaitingVerification,
            ]);

            // Catat status history
            $order->statusHistories()->create([
                'status'     => $order->status->value,
                'notes'      => 'Bukti pembayaran diunggah, menunggu verifikasi admin.',
                'created_by' => $userId,
            ]);

            Log::info('Payment proof uploaded', [
                'order_number' => $order->order_number,
                'payment_id'   => $payment->id,
                'proof_path'   => $path,
            ]);

            return $payment->fresh();
        });
    }

    // ── Admin: Approve Pembayaran ─────────────────────────────────────────────

    /**
     * Admin menyetujui bukti pembayaran manual.
     * Payment → Paid, Order → Confirmed, credit reward points.
     *
     * Validasi:
     * - Status harus AwaitingVerification
     * - Payment proof harus sudah ada (file terupload)
     * - Menggunakan lockForUpdate untuk concurrency safety
     */
    public function approvePayment(string $paymentId, string $adminId): Payment
    {
        return DB::transaction(function () use ($paymentId, $adminId) {
            $payment = Payment::with('order.items.variant.product')
                ->lockForUpdate()
                ->findOrFail($paymentId);

            // ── Guard clauses ─────────────────────────────────────────────

            if ($payment->status !== PaymentStatus::AwaitingVerification) {
                throw ValidationException::withMessages([
                    'payment' => ['Hanya pembayaran dengan status "Menunggu Verifikasi" yang bisa disetujui.'],
                ]);
            }

            // Pastikan bukti pembayaran benar-benar ada di storage
            if (empty($payment->payment_proof_path) || ! Storage::disk('public')->exists($payment->payment_proof_path)) {
                throw ValidationException::withMessages([
                    'payment' => ['File bukti pembayaran tidak ditemukan. Minta customer untuk upload ulang.'],
                ]);
            }

            // ── 1. Update payment status ──────────────────────────────────
            $payment->update([
                'status'      => PaymentStatus::Paid,
                'paid_at'     => now(),
                'verified_by' => $adminId,
                'verified_at' => now(),
            ]);

            // ── 2. Transition order ke Confirmed ──────────────────────────
            $order = $payment->order;
            $order->transitionTo(
                OrderStatus::Confirmed,
                'Pembayaran manual QRIS diverifikasi oleh admin.',
                $adminId
            );

            // ── 3. Credit reward points ───────────────────────────────────
            $pointsEarned = (int) floor($order->subtotal / 1000);

            if ($pointsEarned > 0 && $order->user_id) {
                $user = $order->user;
                if ($user) {
                    $user->increment('points', $pointsEarned);

                    PointTransaction::create([
                        'user_id'        => $user->id,
                        'amount'         => $pointsEarned,
                        'type'           => 'EARN',
                        'description'    => "Poin dari transaksi pesanan #{$order->order_number}",
                        'reference_type' => get_class($order),
                        'reference_id'   => $order->id,
                    ]);
                }
            }

            $order->update(['points_earned' => $pointsEarned]);

            Log::info('Manual payment approved', [
                'payment_id'   => $payment->id,
                'order_number' => $order->order_number,
                'admin_id'     => $adminId,
            ]);

            return $payment->fresh(['order']);
        });
    }

    // ── Admin: Reject Pembayaran ──────────────────────────────────────────────

    /**
     * Admin menolak bukti pembayaran.
     * Payment → Rejected, Order tetap Pending.
     * Customer mendapat perpanjangan waktu 24 jam untuk re-upload.
     */
    public function rejectPayment(string $paymentId, string $adminId, string $reason): Payment
    {
        return DB::transaction(function () use ($paymentId, $adminId, $reason) {
            $payment = Payment::with('order')
                ->lockForUpdate()
                ->findOrFail($paymentId);

            if ($payment->status !== PaymentStatus::AwaitingVerification) {
                throw ValidationException::withMessages([
                    'payment' => ['Hanya pembayaran dengan status "Menunggu Verifikasi" yang bisa ditolak.'],
                ]);
            }

            $payment->update([
                'status'           => PaymentStatus::Rejected,
                'rejection_reason' => $reason,
                'verified_by'      => $adminId,
                'verified_at'      => now(),
                // Perpanjang deadline 24 jam dari sekarang agar customer punya waktu re-upload
                'expired_at'       => now()->addDay(),
            ]);

            // Catat status history di order (order tetap Pending)
            $order = $payment->order;
            $order->statusHistories()->create([
                'status'     => $order->status->value,
                'notes'      => "Bukti pembayaran ditolak: {$reason}",
                'created_by' => $adminId,
            ]);

            Log::info('Manual payment rejected', [
                'payment_id'   => $payment->id,
                'order_number' => $order->order_number,
                'admin_id'     => $adminId,
                'reason'       => $reason,
            ]);

            return $payment->fresh(['order']);
        });
    }

    // ── Admin: List Pending Verifikasi ────────────────────────────────────────

    /**
     * Ambil semua payment manual yang menunggu verifikasi admin.
     */
    public function getPendingVerifications(int $perPage = 20)
    {
        return Payment::where('method', PaymentMethod::ManualQris->value)
            ->where('status', PaymentStatus::AwaitingVerification)
            ->with(['order.user.profile', 'order.shippingAddress'])
            ->latest('updated_at')
            ->paginate($perPage);
    }

    // ── Auto-Expire Manual Payments ──────────────────────────────────────────

    /**
     * Expire pembayaran manual QRIS yang sudah melewati batas waktu 24 jam.
     * Dipanggil via scheduled command atau admin endpoint.
     *
     * Hanya memproses payment dengan status:
     * - Waiting (belum upload bukti, waktu habis)
     * - Rejected (ditolak, tapi tidak re-upload dalam 24 jam)
     *
     * TIDAK memproses AwaitingVerification karena admin mungkin belum cek.
     */
    public function expireOverduePayments(): array
    {
        $payments = Payment::where('method', PaymentMethod::ManualQris->value)
            ->whereIn('status', [
                PaymentStatus::Waiting->value,
                PaymentStatus::Rejected->value,
            ])
            ->where('expired_at', '<', now())
            ->with(['order.items.variant'])
            ->get();

        $expiredCount = 0;
        $failedCount  = 0;

        foreach ($payments as $payment) {
            try {
                DB::transaction(function () use ($payment, &$expiredCount) {
                    $payment->update(['status' => PaymentStatus::Expired]);

                    $order = $payment->order;

                    // Kembalikan stok secara atomic
                    $items = $order->items->sortBy('product_variant_id');
                    foreach ($items as $item) {
                        if ($item->variant) {
                            ProductVariant::where('id', $item->product_variant_id)
                                ->lockForUpdate()
                                ->first()
                                ?->increment('stock', $item->quantity);
                        }
                    }

                    // Kembalikan reward points jika digunakan
                    if ($order->points_used > 0) {
                        $user = $order->user;
                        if ($user) {
                            $user->increment('points', $order->points_used);

                            PointTransaction::create([
                                'user_id'        => $user->id,
                                'amount'         => $order->points_used,
                                'type'           => 'REFUND',
                                'description'    => "Pengembalian poin - pesanan #{$order->order_number} expired",
                                'reference_type' => get_class($order),
                                'reference_id'   => $order->id,
                            ]);
                        }
                    }

                    $order->transitionTo(
                        OrderStatus::Cancelled,
                        'Dibatalkan sistem: Batas waktu pembayaran manual QRIS (24 jam) telah habis.'
                    );

                    $expiredCount++;
                });
            } catch (\Exception $e) {
                Log::error("Gagal expire manual payment ID: {$payment->id}", [
                    'error' => $e->getMessage(),
                ]);
                $failedCount++;
            }
        }

        return [
            'total_processed' => $payments->count(),
            'expired'         => $expiredCount,
            'failed'          => $failedCount,
        ];
    }
}
