<?php
// database/seeders/AdminUserSeeder.php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@garamcapkapal.id'],
            [
                'name'              => 'Super Admin',
                'email'             => 'admin@garamcapkapal.id',
                'password'          => bcrypt('Admin@GaramCapKapal2025!'),
                'user_type'         => 'admin',
                'status'            => 'active',
                'email_verified_at' => now(),
                'referral_code'     => 'ADMINGCK1',
            ]
        );

//        $admin->assignRole('admin');

        // Buat reward_points record untuk admin
//        $admin->rewardPoint()->firstOrCreate([
//            'user_id' => $admin->id,
//        ]);

        $this->command->info("Admin user created: {$admin->email}");
    }
}
