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

        // We expect testConnection to NEVER be called
        $mockBackupService->shouldReceive('testConnection')->never();

        // We might need to mock listBackups if index is hit, but we are hitting update
        // We need to bind the mock
        $this->instance(BackupService::class, $mockBackupService);

        // Send request without 'enabled'
        $response = $this->actingAs($admin)->post(route('admin.backups.update'), [
            'driver' => 's3',
            'username' => 'key',
            'password' => 'secret',
            'bucket' => 'bucket',
            'region' => 'us-east-1',
            'retention_days' => 7,
            // 'enabled' is missing -> false
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

        // Mock BackupService
        $mockBackupService = Mockery::mock(BackupService::class);

        // We expect testConnection to be called ONCE
        $mockBackupService->shouldReceive('testConnection')
            ->once()
            ->andReturn(true);

        $this->instance(BackupService::class, $mockBackupService);

        // Send request with 'enabled'
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
}
