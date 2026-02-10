<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Package;
use App\Models\Container;
use App\Services\BackupService;
use App\Services\DockerService;
use App\Services\PortAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Mockery;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'user']);

        Storage::fake('backup');
        Storage::fake('local');
    }

    /** @test */
    public function it_restores_instances_from_backups_with_missing_package()
    {
        // 1. Setup Data
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Ensure no packages exist
        Package::truncate();

        $instanceName = 'test_instance_fresh';

        // Create Fake Backup Files
        Storage::disk('backup')->put("{$instanceName}/key.txt", 'test-key-content');
        Storage::disk('backup')->put("{$instanceName}/backup-2023-01-01.sql", 'SQL DUMP CONTENT');

        // 2. Mock Services
        $mockBackupService = Mockery::mock(BackupService::class);
        $mockBackupService->shouldReceive('configureDisk')->andReturn(true);
        $this->instance(BackupService::class, $mockBackupService);

        $mockDockerService = Mockery::mock(DockerService::class);
        $mockDockerService->shouldReceive('getDockerGatewayIp')->andReturn('172.17.0.1');

        $mockContainerInstance = Mockery::mock();
        $mockContainerInstance->shouldReceive('getShortDockerIdentifier')->andReturn('docker456');

        $mockDockerService->shouldReceive('createContainer')
            ->once()
            ->andReturn($mockContainerInstance);

        $mockDockerService->shouldReceive('stopContainer')->andReturn(true);
        $mockDockerService->shouldReceive('startContainer')->andReturn(true);

        $this->instance(DockerService::class, $mockDockerService);

        $mockPortAllocator = Mockery::mock(PortAllocator::class);
        $mockPortAllocator->shouldReceive('allocate')->andReturn(5679);
        $this->instance(PortAllocator::class, $mockPortAllocator);

        Process::fake(['*' => Process::result()]);

        // 3. Execute
        $response = $this->actingAs($admin)->post(route('admin.backups.restore'), [
            'folders' => [$instanceName]
        ]);

        // 4. Assertions
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check if package was automatically created
        $this->assertDatabaseHas('packages', [
            'name' => 'Standard',
            'cpu_limit' => 2,
        ]);

        $package = Package::where('name', 'Standard')->first();

        // Check instance created and linked to new package
        $this->assertDatabaseHas('containers', [
            'name' => $instanceName,
            'package_id' => $package->id,
            'user_id' => $admin->id,
        ]);
    }
}
