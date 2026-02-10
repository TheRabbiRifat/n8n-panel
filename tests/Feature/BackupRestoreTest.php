<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Package;
use App\Models\Container;
use App\Models\BackupSetting;
use App\Services\BackupService;
use App\Services\DockerService;
use App\Services\PortAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Mockery;
use Illuminate\Support\Carbon;

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
        $hostname = gethostname();
        $folderPath = "{$hostname}/{$instanceName}";

        // Create Fake Backup Files
        Storage::disk('backup')->put("{$folderPath}/key.txt", 'test-key-content');
        Storage::disk('backup')->put("{$folderPath}/backup-2023-01-01.sql", 'SQL DUMP CONTENT');

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
        // Simulate folders as full paths
        $response = $this->actingAs($admin)->post(route('admin.backups.restore'), [
            'folders' => [$folderPath]
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

    /** @test */
    public function it_restores_instances_using_metadata()
    {
        // 1. Setup Data
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create(['email' => 'original@example.com']);
        $user->assignRole('user');

        $instanceName = 'meta_instance';
        $hostname = gethostname();
        $folderPath = "{$hostname}/{$instanceName}";

        // Create Metadata
        $metadata = [
            'version' => '1.0',
            'n8n_version' => '1.0.0',
            'encryption_key' => 'meta-key-content',
            'package' => [
                'name' => 'CustomPlan',
                'cpu_limit' => 4,
                'ram_limit' => 8,
                'disk_limit' => 50,
            ],
            'owner' => [
                'email' => 'original@example.com',
            ]
        ];

        Storage::disk('backup')->put("{$folderPath}/metadata.json", json_encode($metadata));
        Storage::disk('backup')->put("{$folderPath}/backup-2023-01-01.sql", 'SQL DUMP CONTENT');

        // 2. Mock Services
        $mockBackupService = Mockery::mock(BackupService::class);
        $mockBackupService->shouldReceive('configureDisk')->andReturn(true);
        $this->instance(BackupService::class, $mockBackupService);

        $mockDockerService = Mockery::mock(DockerService::class);
        $mockDockerService->shouldReceive('getDockerGatewayIp')->andReturn('172.17.0.1');

        $mockContainerInstance = Mockery::mock();
        $mockContainerInstance->shouldReceive('getShortDockerIdentifier')->andReturn('docker789');

        $mockDockerService->shouldReceive('createContainer')
            ->once()
            ->withArgs(function ($image, $name, $port, $internalPort, $cpu, $ram, $env) {
                return $image === 'n8nio/n8n:1.0.0' &&
                       $cpu == 4 &&
                       $ram == 8 &&
                       str_contains(json_encode($env), 'meta-key-content');
            })
            ->andReturn($mockContainerInstance);

        $mockDockerService->shouldReceive('stopContainer')->andReturn(true);
        $mockDockerService->shouldReceive('startContainer')->andReturn(true);

        $this->instance(DockerService::class, $mockDockerService);

        $mockPortAllocator = Mockery::mock(PortAllocator::class);
        $mockPortAllocator->shouldReceive('allocate')->andReturn(5680);
        $this->instance(PortAllocator::class, $mockPortAllocator);

        Process::fake(['*' => Process::result()]);

        // 3. Execute
        $response = $this->actingAs($admin)->post(route('admin.backups.restore'), [
            'folders' => [$folderPath]
        ]);

        // 4. Assertions
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify Custom Package Created
        $this->assertDatabaseHas('packages', [
            'name' => 'CustomPlan',
            'cpu_limit' => 4,
            'ram_limit' => 8,
        ]);
        $package = Package::where('name', 'CustomPlan')->first();

        // Verify Instance Linked to Correct User and Package
        $this->assertDatabaseHas('containers', [
            'name' => $instanceName,
            'package_id' => $package->id,
            'user_id' => $user->id, // Should belong to 'original@example.com'
            'image_tag' => '1.0.0',
        ]);

        // Verify Key in DB
        $container = Container::where('name', $instanceName)->first();
        $this->assertStringContainsString('meta-key-content', $container->environment);
    }

    /** @test */
    public function it_cleans_up_old_backups()
    {
        // 1. Setup
        BackupSetting::create([
            'driver' => 'local',
            'retention_days' => 5,
            'enabled' => true
        ]);

        $service = new BackupService();
        // Since we are not mocking the service completely (we need the method to run),
        // we might need to partially mock or just rely on Storage::fake
        // BackupService methods listBackups etc rely on configureDisk.
        // But cleanupOldBackups relies on Storage facade which is faked.

        // 2. Create files
        $hostname = gethostname();
        $oldFile = "{$hostname}/instance1/backup-old.sql";
        $newFile = "{$hostname}/instance1/backup-new.sql";
        $keyFile = "{$hostname}/instance1/key.txt";

        // Create with specific timestamps
        // Storage::fake doesn't easily support setting mtime directly via put()
        // But we can manipulate the underlying adapter or mock the 'lastModified' call?
        // Mocking 'lastModified' is tricky with Facade.
        // A better approach: Mock Storage::disk('backup') entirely?
        // Or if we use `Illuminate\Support\Carbon::setTestNow()` it won't affect `filemtime`.

        // Let's rely on Mockery for Storage since we need return values for lastModified

        // We need to bypass the 'configureDisk' in cleanupOldBackups?
        // No, cleanupOldBackups calls Storage::disk('backup')->allFiles().
        // We can just rely on the fact that we can manipulate the timestamp if we were using real files,
        // but with Fake, maybe not.

        // Alternative: Refactor BackupService to be testable or Mock the disk behavior?
        // Let's use a partial mock of BackupService to verify logic?
        // No, we want to test the logic inside cleanupOldBackups.

        // Let's mock the Storage facade behavior for `lastModified`.
        // Storage::shouldReceive('disk')->with('backup')->andReturn($diskMock);

        $diskMock = Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $diskMock->shouldReceive('allFiles')->andReturn([$oldFile, $newFile, $keyFile]);

        // Old file: 10 days ago
        $diskMock->shouldReceive('lastModified')->with($oldFile)->andReturn(now()->subDays(10)->timestamp);
        // New file: 1 day ago
        $diskMock->shouldReceive('lastModified')->with($newFile)->andReturn(now()->subDays(1)->timestamp);
        // Key file: 10 days ago (but should be ignored by extension check)
        // Actually, the loop checks extension first. So lastModified won't be called for key.txt.

        // Expect deletion of old file
        $diskMock->shouldReceive('delete')->with($oldFile)->once();

        // Expect NO deletion of new file
        $diskMock->shouldReceive('delete')->with($newFile)->never();

        Storage::shouldReceive('disk')->with('backup')->andReturn($diskMock);

        // 3. Act
        $service->cleanupOldBackups();

        // 4. Assert (Mockery assertions happen on teardown)
        $this->assertTrue(true);
    }
}
