<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Container;
use App\Services\DockerService;
use Spatie\Permission\Models\Role;
use Mockery;

class ResellerStatsCachingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'reseller']);
        Role::create(['name' => 'user']);
    }

    public function test_reseller_stats_uses_caching_for_docker_containers()
    {
        // 1. Setup Admin and Reseller
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $reseller = User::factory()->create(['username' => 'reseller1']);
        $reseller->assignRole('reseller');

        // 2. Create a container for the reseller
        $container = Container::factory()->create([
             'user_id' => $reseller->id,
             'name' => 'instance1',
             'docker_id' => 'abc123456789'
        ]);

        // 3. Mock DockerService
        // We expect listContainers to be called exactly ONCE if caching is working.
        // Currently (before fix), it will be called TWICE.
        $this->mock(DockerService::class, function ($mock) {
            $mock->shouldReceive('listContainers')
                 ->times(1)
                 ->andReturn([
                     [
                         'id' => 'abc123456789',
                         'name' => 'instance1',
                         'image' => 'n8nio/n8n:latest',
                         'status' => 'Up 1 hour',
                         'state' => 'running',
                         'ports' => '0.0.0.0:5678->5678/tcp'
                     ]
                 ]);
        });

        // 4. Act as Admin and call the endpoint TWICE
        $response1 = $this->actingAs($admin)
                          ->getJson("/api/integration/resellers/{$reseller->username}/stats");

        $response1->assertStatus(200)
                  ->assertJson(['status' => 'success']);

        $response2 = $this->actingAs($admin)
                          ->getJson("/api/integration/resellers/{$reseller->username}/stats");

        $response2->assertStatus(200)
                  ->assertJson(['status' => 'success']);
    }
}
