<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update Packages Table
        Schema::table('packages', function (Blueprint $table) {
            $table->string('type')->default('instance')->after('name'); // 'instance' or 'reseller'
            $table->integer('instance_count')->nullable()->after('disk_limit'); // Only for reseller packages
        });

        // 2. Update Users Table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('reseller_id')->constrained('packages')->nullOnDelete();
        });

        // 3. Data Migration
        // Set all existing packages to 'instance' (already handled by default)

        // Migrate Resellers
        // We can't use Eloquent easily here if models aren't updated yet, but DB facade works.
        $resellers = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'reseller')
            ->select('users.*')
            ->get();

        foreach ($resellers as $reseller) {
            // Create a custom package for this reseller to preserve their limits
            // Assuming current 'instance_limit' exists on user.
            $limit = $reseller->instance_limit ?? 10;

            $pkgId = DB::table('packages')->insertGetId([
                'user_id' => $reseller->id, // Owned by the reseller themselves? Or Null/Admin? Packages usually owned by Admin.
                                            // Existing packages table has user_id.
                                            // Let's assign to Admin (id 1) or the reseller.
                                            // If assigned to reseller, they might edit it?
                                            // Let's assign to Admin (1) for safety, or null if nullable.
                                            // Schema says: foreignId('user_id').constrained().
                'user_id' => 1, // Default Admin
                'name' => 'Legacy Reseller Package - ' . $reseller->username,
                'type' => 'reseller',
                'cpu_limit' => 100, // Default generous limit for legacy
                'ram_limit' => 100, // Default generous limit (100GB)
                'disk_limit' => 1000, // Default generous limit
                'instance_count' => $limit,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->where('id', $reseller->id)->update(['package_id' => $pkgId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropColumn('package_id');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['type', 'instance_count']);
        });
    }
};
