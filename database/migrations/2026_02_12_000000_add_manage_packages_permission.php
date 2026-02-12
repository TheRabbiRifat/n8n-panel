<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permission if not exists
        $permission = Permission::firstOrCreate(['name' => 'manage_packages']);

        // Assign to roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo($permission);

        $reseller = Role::firstOrCreate(['name' => 'reseller']);
        $reseller->givePermissionTo($permission);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optional: Remove permission
        $permission = Permission::where('name', 'manage_packages')->first();
        if ($permission) {
            $permission->delete();
        }
    }
};
