<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\BackupSetting;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;
use Spatie\Permission\Models\Role;

class BackupSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'user']);
    }

    /** @test */
    public function it_skips_connection_test_when_disabling_backups()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Mock BackupService
        $mockBackupService = Mockery::mock(BackupService::class);
        $mockBackupService->shouldReceive('testConnection')->never();

        $this->instance(BackupService::class, $mockBackupService);

        $response = $this->actingAs($admin)->post(route('admin.backups.update'), [
            'driver' => 's3',
            'username' => 'key',
            'password' => 'secret',
            'bucket' => 'bucket',
            'region' => 'us-east-1',
            'retention_days' => 7,
            // enabled missing
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('backup_settings', [
            'driver' => 's3',
            'enabled' => false,
        ]);
    }

    /** @test */
    public function it_runs_connection_test_when_enabling_backups()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $mockBackupService = Mockery::mock(BackupService::class);
        $mockBackupService->shouldReceive('testConnection')->once()->andReturn(true);
        $this->instance(BackupService::class, $mockBackupService);

        $response = $this->actingAs($admin)->post(route('admin.backups.update'), [
            'driver' => 's3',
            'username' => 'key',
            'password' => 'secret',
            'bucket' => 'bucket',
            'region' => 'us-east-1',
            'retention_days' => 7,
            'enabled' => 'on',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('backup_settings', [
            'driver' => 's3',
            'enabled' => true,
        ]);
    }

    /** @test */
    public function it_saves_ftp_ignore_passive_ip_setting()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $mockBackupService = Mockery::mock(BackupService::class);
        $mockBackupService->shouldReceive('testConnection')->twice()->andReturn(true);
        $this->instance(BackupService::class, $mockBackupService);

        // Test Unchecked (False)
        $response = $this->actingAs($admin)->post(route('admin.backups.update'), [
            'driver' => 'ftp',
            'host' => 'ftp.example.com',
            'username' => 'user',
            'password' => 'pass',
            'retention_days' => 7,
            'enabled' => 'on',
            // ftp_ignore_passive_ip missing -> false
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('backup_settings', [
            'driver' => 'ftp',
            'ftp_ignore_passive_ip' => false,
        ]);

        // Test Checked (True)
        $response = $this->actingAs($admin)->post(route('admin.backups.update'), [
            'driver' => 'ftp',
            'host' => 'ftp.example.com',
            'username' => 'user',
            'password' => 'pass',
            'retention_days' => 7,
            'enabled' => 'on',
            'ftp_ignore_passive_ip' => 'on',
        ]);

        $this->assertDatabaseHas('backup_settings', [
            'driver' => 'ftp',
            'ftp_ignore_passive_ip' => true,
        ]);
    }
}
