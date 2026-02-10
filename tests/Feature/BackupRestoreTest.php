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
    public function it_restores_instances_from_backups()
    {
        // 1. Setup Data
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $package = Package::create([
            'name' => 'Default',
            'cpu_limit' => 1,
            'ram_limit' => 1,
            'disk_limit' => 10,
            'price' => 0,
            'type' => 'instance',
            'user_id' => $admin->id
        ]);

        $instanceName = 'test_instance';

        // Create Fake Backup Files
        Storage::disk('backup')->put("{$instanceName}/key.txt", 'test-key-content');
        // Create dummy SQL file with timestamp
        Storage::disk('backup')->put("{$instanceName}/backup-2023-01-01.sql", 'SQL DUMP CONTENT');
        // Ensure timestamp is set? Storage fake handles it.

        // 2. Mock BackupService
        $mockBackupService = Mockery::mock(BackupService::class);
        $mockBackupService->shouldReceive('configureDisk')->andReturn(true);
        // listBackups not called in restore
        $this->instance(BackupService::class, $mockBackupService);

        // 3. Mock DockerService
        $mockDockerService = Mockery::mock(DockerService::class);
        $mockDockerService->shouldReceive('getDockerGatewayIp')->andReturn('172.17.0.1');

        // Mock createContainer result
        $mockContainerInstance = Mockery::mock();
        $mockContainerInstance->shouldReceive('getShortDockerIdentifier')->andReturn('docker123');

        $mockDockerService->shouldReceive('createContainer')
            ->once()
            ->withArgs(function ($image, $name, $port, $internalPort, $cpu, $ram, $env, $volumes, $labels, $domain, $email, $dbId, $dbConfig, $panelDbUser) use ($instanceName) {
                return $name === $instanceName &&
                       str_contains(json_encode($env), 'test-key-content');
            })
            ->andReturn($mockContainerInstance);

        $mockDockerService->shouldReceive('stopContainer')->andReturn(true);
        $mockDockerService->shouldReceive('startContainer')->andReturn(true);

        $this->instance(DockerService::class, $mockDockerService);

        // 4. Mock PortAllocator
        $mockPortAllocator = Mockery::mock(PortAllocator::class);
        $mockPortAllocator->shouldReceive('allocate')->andReturn(5678);
        $this->instance(PortAllocator::class, $mockPortAllocator);

        // 5. Mock Process for DB Import
        Process::fake([
            '*' => Process::result(),
        ]);

        // 6. Execute Request
        $response = $this->actingAs($admin)->post(route('admin.backups.restore'), [
            'folders' => [$instanceName]
        ]);

        // 7. Assertions
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check DB creation
        $this->assertDatabaseHas('containers', [
            'name' => $instanceName,
            'user_id' => $admin->id,
            'package_id' => $package->id,
            'port' => 5678,
            'docker_id' => 'docker123',
        ]);

        // Check key injection in DB
        $container = Container::where('name', $instanceName)->first();
        $this->assertStringContainsString('test-key-content', $container->environment);
    }
}
