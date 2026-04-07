<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\{Role, Permission};

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

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'id'   => Str::uuid(),
                'name' => $permission,
                'guard_name' => 'sanctum'
            ]);
        }

        // Roles
        $admin  = Role::firstOrCreate(['id' => Str::uuid(), 'name' => 'admin',       'guard_name' => 'sanctum']);
        $editor = Role::firstOrCreate(['id' => Str::uuid(), 'name' => 'editor',      'guard_name' => 'sanctum']);
        $agen   = Role::firstOrCreate(['id' => Str::uuid(), 'name' => 'agen',        'guard_name' => 'sanctum']);
        $dist   = Role::firstOrCreate(['id' => Str::uuid(), 'name' => 'user',        'guard_name' => 'sanctum']);

        // Cannot use syncPermissions() because it doesn't work with UUIDs', assign all roles to admin
//        $admin->syncPermissions(Permission::all());

//        $editor->syncPermissions([
//            'cms.view', 'cms.create', 'cms.edit', 'cms.publish',
//            'products.view', 'promos.view', 'admin.dashboard',
//        ]);

        $this->command->info('Roles and permissions seeded!');
    }
}
