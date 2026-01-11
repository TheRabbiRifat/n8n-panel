<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            'view_dashboard',
            'manage_instances',
            'manage_users',
            'manage_settings',
            'view_logs',
            'view_system',
            'manage_roles',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create Roles and Assign Permissions
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        $reseller = Role::firstOrCreate(['name' => 'reseller']);
        $reseller->syncPermissions(['view_dashboard', 'manage_instances']);

        $client = Role::firstOrCreate(['name' => 'client']);
        $client->syncPermissions(['view_dashboard', 'manage_instances']);
    }
}
