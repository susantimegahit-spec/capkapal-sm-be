<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\{Role, Permission};

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Products
            'products.view', 'products.create', 'products.edit', 'products.delete',
            // Orders
            'orders.view', 'orders.manage',
            // Users
            'users.view', 'users.manage',
            // CMS
            'cms.view', 'cms.create', 'cms.edit', 'cms.delete', 'cms.publish',
            // Promos
            'promos.view', 'promos.manage',
            // Rewards
            'rewards.view', 'rewards.manage',
            // Admin
            'admin.dashboard', 'admin.reports', 'admin.settings',
        ];

        // Pastikan guard_name diset ke 'api' (sesuai dengan model User Anda)
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'api'],
                ['id' => Str::uuid()]
            );
        }

        // Roles dengan guard_name 'api'
        $admin  = Role::firstOrCreate(['name' => 'admin',  'guard_name' => 'api'], ['id' => Str::uuid()]);
        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'api'], ['id' => Str::uuid()]);
        $agen   = Role::firstOrCreate(['name' => 'agen',   'guard_name' => 'api'], ['id' => Str::uuid()]);
        $user   = Role::firstOrCreate(['name' => 'user',   'guard_name' => 'api'], ['id' => Str::uuid()]);

        // Trik UUID: Gunakan array berisi string nama permission, bukan object modelnya
        $admin->syncPermissions(Permission::pluck('name')->toArray());

        $editor->syncPermissions([
            'cms.view', 'cms.create', 'cms.edit', 'cms.publish',
            'products.view', 'promos.view', 'admin.dashboard',
        ]);

        $this->command->info('Roles and permissions seeded!');
    }
}
