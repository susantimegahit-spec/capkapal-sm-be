<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RewardTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            [
                'id'                 => Str::uuid(),
                'name'               => 'Bronze',
                'min_points'         => 0,
                'discount_pct'       => 0,
                'points_multiplier'  => 1,
                'perks'              => json_encode([]),
                'badge_color'        => '#CD7F32',
                'sort_order'         => 1,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'id'                 => Str::uuid(),
                'name'               => 'Silver',
                'min_points'         => 1000,
                'discount_pct'       => 2.00,
                'points_multiplier'  => 2,
                'perks'              => json_encode(['free_ongkir_min50k']),
                'badge_color'        => '#C0C0C0',
                'sort_order'         => 2,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'id'                 => Str::uuid(),
                'name'               => 'Gold',
                'min_points'         => 5000,
                'discount_pct'       => 5.00,
                'points_multiplier'  => 3,
                'perks'              => json_encode(['free_ongkir_min30k', 'early_access_promo']),
                'badge_color'        => '#FFD700',
                'sort_order'         => 3,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'id'                 => Str::uuid(),
                'name'               => 'Platinum',
                'min_points'         => 15000,
                'discount_pct'       => 10.00,
                'points_multiplier'  => 5,
                'perks'              => json_encode(['free_ongkir', 'priority_support', 'exclusive_deals']),
                'badge_color'        => '#E5E4E2',
                'sort_order'         => 4,
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
        ];

        DB::table('reward_tiers')->insert($tiers);
        $this->command->info('Reward tiers seeded: Bronze, Silver, Gold, Platinum');
    }
}
