<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                // Use part of email before @ as default username
                $prefix = explode('@', $user->email)[0];
                $baseUsername = Str::slug($prefix, '_');

                // Fallback to name if email prefix is not slug-friendly or empty
                if (empty($baseUsername)) {
                    $baseUsername = Str::slug($user->name, '_');
                }

                // Ultimate fallback
                if (empty($baseUsername)) {
                    $baseUsername = 'user';
                }

                // Truncate to ensure space for suffixes if needed (max 255)
                $baseUsername = substr($baseUsername, 0, 240);

                $username = $baseUsername;
                $counter = 1;

                // Ensure uniqueness logic: loop until a unique username is found
                while (DB::table('users')->where('username', $username)->where('id', '!=', $user->id)->exists()) {
                    $username = $baseUsername . '_' . $counter;
                    $counter++;
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
