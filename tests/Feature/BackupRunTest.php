<?php

namespace Tests\Feature;

use App\Models\BackupSetting;
use App\Models\Container;
use App\Models\User;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackupRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backup');
        Storage::fake('local');

        // Seed roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'user']);
    }

    /** @test */
    public function it_runs_backup_command_successfully()
    {
        // 1. Setup
        BackupSetting::create([
            'driver' => 'local',
            'enabled' => true,
            'retention_days' => 5
        ]);

        $user = User::factory()->create();
        $package = Package::factory()->create();

        Container::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'name' => 'test-container',
            'docker_id' => 'docker123',
            'db_host' => '127.0.0.1',
            'db_port' => 5432,
            'db_database' => 'n8n_test',
            'db_username' => 'n8n_user',
            'db_password' => 'secret',
            'environment' => json_encode(['N8N_ENCRYPTION_KEY' => 'key123']),
            'image_tag' => 'latest',
            'port' => 5678,
            'domain' => 'test.localhost'
        ]);

        // Mock Process
        Process::fake(function ($process) {
            $cmd = $process->command;
            $cmdStr = is_array($cmd) ? implode(' ', $cmd) : $cmd;

            if (str_contains($cmdStr, 'pg_dump') && str_contains($cmdStr, '--version')) {
                return Process::result('pg_dump (PostgreSQL) 14.5');
            }
            if (str_contains($cmdStr, 'pg_dump')) {
                return Process::result('SQL DUMP OUTPUT');
            }
            if (str_contains($cmdStr, 'which')) {
                return Process::result('/usr/bin/pg_dump');
            }
            return Process::result();
        });

        // 2. Run Command
        $exitCode = Artisan::call('backup:run');

        // 3. Assert
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Instance test-container: Success', $output);
    }


    /** @test */
    public function it_handles_manual_trigger_via_controller()
    {
        // 1. Setup
        BackupSetting::create([
            'driver' => 'local',
            'enabled' => true,
            'retention_days' => 5
        ]);

        // Mock Process for pg_dump
        Process::fake(function ($process) {
            $cmd = $process->command;
            $cmdStr = is_array($cmd) ? implode(' ', $cmd) : $cmd;

            if (str_contains($cmdStr, 'pg_dump') && str_contains($cmdStr, '--version')) {
                return Process::result('pg_dump (PostgreSQL) 14.5');
            }
            if (str_contains($cmdStr, 'pg_dump')) {
                return Process::result('SQL DUMP OUTPUT');
            }
            return Process::result();
        });

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // 2. Request
        $response = $this->actingAs($admin)->post(route('admin.backups.run'));

        // 3. Assert
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
