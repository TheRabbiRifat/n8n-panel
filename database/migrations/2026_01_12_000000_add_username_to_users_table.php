<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
        });

        // Populate existing users with username derived from email or random string
        DB::table('users')->orderBy('id')->chunk(100, function ($users) {
            foreach ($users as $user) {
                // Use part of email before @ as default username, or fallback to name, or random
                $username = explode('@', $user->email)[0];

                // Ensure uniqueness basic check (collisions might occur if emails are similar, but for migration fix sufficient)
                // If collision, append ID
                if (DB::table('users')->where('username', $username)->exists()) {
                    $username = $username . '_' . $user->id;
                }

                DB::table('users')->where('id', $user->id)->update(['username' => $username]);
            }
        });

        // Now enforce non-null and unique
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
