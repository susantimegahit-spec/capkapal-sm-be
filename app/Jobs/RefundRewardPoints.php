<?php

namespace App\Jobs;

use App\Modules\Order\Services\RewardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class RefundRewardPoints implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'high';

    public function __construct(
        private readonly string $userId,
        private readonly int    $points,
        private readonly string $orderId,
    ) {}

    public function handle(RewardService $rewardService): void
    {
        $rewardService->refundPoints($this->userId, $this->points, $this->orderId);
    }
}
