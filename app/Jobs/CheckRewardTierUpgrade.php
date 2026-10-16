<?php
// app/Jobs/CheckRewardTierUpgrade.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\DB;

class CheckRewardTierUpgrade implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly string $userId) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $rp = DB::table('reward_points')->where('user_id', $this->userId)->first();

        if (! $rp) {
            return;
        }

        $newTier = DB::table('reward_tiers')
            ->where('min_points', '<=', $rp->lifetime_earned)
            ->orderByDesc('min_points')
            ->first();

        if (! $newTier) {
            return;
        }

        DB::table('user_tiers')->updateOrInsert(
            ['user_id' => $this->userId],
            ['tier_id' => $newTier->id, 'achieved_at' => now(), 'updated_at' => now()]
        );
    }
}
