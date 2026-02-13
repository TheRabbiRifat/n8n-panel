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

        // Revoke manage_packages from reseller
        $reseller = Role::where('name', 'reseller')->first();
        if ($reseller) {
            $reseller->revokePermissionTo('manage_packages');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore permission
        $reseller = Role::where('name', 'reseller')->first();
        if ($reseller) {
            $reseller->givePermissionTo('manage_packages');
        }
    }
};
