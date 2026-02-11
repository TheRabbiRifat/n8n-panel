<?php

namespace Tests\Feature;

use App\Models\BackupSetting;
use App\Models\User;
use App\Services\BackupService;
use App\Services\DockerService;
use App\Services\PortAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Mockery;

class BackupSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles
        Role::create(['name' => 'admin']);

        // Mock Services
        $this->mock(DockerService::class);
        $this->mock(PortAllocator::class);
        Storage::fake('backup');
        Storage::fake('backup_test');
    }

    /** @test */
    public function it_blocks_saving_enabled_settings_on_connection_failure()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Mock BackupService to fail testConnection
        $mockBackupService = Mockery::mock(BackupService::class);
        $mockBackupService->shouldReceive('testConnection')->andThrow(new \Exception('Connection failed'));
        // listBackups is called in index/update sometimes? No, update doesn't call listBackups.

        $this->instance(BackupService::class, $mockBackupService);

        $response = $this->actingAs($admin)->post(route('admin.backups.update'), [
            'driver' => 'ftp',
            'host' => 'invalid-host',
            'username' => 'user',
            'password' => 'pass',
            'enabled' => 'on', // Checkbox sends 'on' or similar, but validation handles presence
            'retention_days' => 30,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('backup_settings', 0); // Should not save
    }

    /** @test */
    public function it_saves_settings_if_disabled_even_if_connection_would_fail()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Mock BackupService - testConnection should NOT be called
        $mockBackupService = Mockery::mock(BackupService::class);
        $mockBackupService->shouldReceive('testConnection')->never();

        $this->instance(BackupService::class, $mockBackupService);

        $response = $this->actingAs($admin)->post(route('admin.backups.update'), [
            'driver' => 'ftp',
            'host' => 'invalid-host',
            'username' => 'user',
            'password' => 'pass',
            // 'enabled' => 'on', // NOT sending enabled
            'retention_days' => 30,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('backup_settings', [
            'driver' => 'ftp',
            'enabled' => false,
        ]);
    }
}
