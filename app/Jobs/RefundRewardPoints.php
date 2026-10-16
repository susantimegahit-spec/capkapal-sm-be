<?php

namespace App\Jobs;

use App\Modules\Order\Services\RewardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\DB;

class RefundRewardPoints implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly string $userId,
        private readonly int    $points,
        private readonly string $orderId,
    ) {
        $this->onQueue('high');
    }

    public function handle(RewardService $rewardService): void
    {
        // Idempotency guard: cek apakah poin untuk order ini sudah pernah di-refund.
        // Mencegah double-refund jika job di-retry oleh queue worker.
        $alreadyRefunded = DB::table('reward_transactions')
            ->where('user_id', $this->userId)
            ->where('type', 'refund')
            ->where('ref_id', $this->orderId)
            ->exists();

        if ($alreadyRefunded) {
            return;
        }

        $rewardService->refundPoints($this->userId, $this->points, $this->orderId);
    }
}
