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
        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->string('driver')->default('local'); // local, s3, ftp
            $table->string('host')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('port')->nullable();
            $table->string('encryption')->nullable(); // For FTP
            $table->string('bucket')->nullable(); // For S3
            $table->string('region')->nullable(); // For S3
            $table->string('endpoint')->nullable(); // For S3
            $table->string('path')->nullable(); // Root path
            $table->string('cron_expression')->default('0 2 * * *'); // Daily at 2AM
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_settings');
    }
};
