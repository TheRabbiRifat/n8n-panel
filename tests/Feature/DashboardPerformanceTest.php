<?php

namespace Tests\Feature;

use App\Models\Container;
use App\Models\Package;
use App\Models\User;
use App\Services\DockerService;
use App\Services\ServiceManager;
use App\Services\SystemStatusService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_load_time_with_many_containers()
    {
        // 1. Setup Data
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        $package = Package::factory()->create([
            'user_id' => $user->id
        ]);

        // Seed 1000 containers
        Container::factory()->count(1000)->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
        ]);

        // 2. Mock Services
        $this->mock(DockerService::class, function ($mock) {
            $mock->shouldReceive('listContainers')->andReturn([]);
        });

        $this->mock(SystemStatusService::class, function ($mock) {
             $mock->shouldReceive('getSystemStats')->andReturn([
                 'cpu' => '10%',
                 'ram' => ['percent' => 10, 'used' => 100, 'total' => 1000],
                 'disk' => ['percent' => 10, 'used_gb' => 10, 'total_gb' => 100],
                 'loads' => ['1' => 0.1, '5' => 0.1],
                 'hostname' => 'test',
                 'os' => 'Linux',
                 'kernel' => '5.4',
                 'ips' => '127.0.0.1',
                 'uptime' => '1 day',
             ]);
        });

        $this->mock(ServiceManager::class, function ($mock) {
            $mock->shouldReceive('getStatus')->andReturn('active');
        });

        // 3. Measure Time & Query Log
        DB::enableQueryLog();

        $start = microtime(true);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $end = microtime(true);
        $duration = $end - $start;

        $response->assertStatus(200);

        // Output duration
        echo "\nDashboard load time with 1000 containers: " . number_format($duration, 4) . " seconds\n";

        $log = DB::getQueryLog();
        $hasPaginationLimit = false;
        $hasUnlimitedSelect = false;

        // Check queries for 'select * from "containers"'
        foreach ($log as $entry) {
            $query = strtolower($entry['query']);
            // Check if it's querying containers table with select *
            if ((str_contains($query, 'select * from "containers"') || str_contains($query, 'select * from `containers`'))) {
                echo "Query found: " . $query . "\n";
                if (str_contains($query, 'limit 5')) {
                    $hasPaginationLimit = true;
                } else {
                    $hasUnlimitedSelect = true;
                }
            }
        }

        if ($hasPaginationLimit && !$hasUnlimitedSelect) {
             echo "Optimization VERIFIED: Found LIMIT query and no UNLIMITED query.\n";
        } elseif ($hasUnlimitedSelect) {
             echo "Optimization FAILED: Found UNLIMITED query.\n";
        } else {
             echo "Optimization STATUS UNKNOWN: No relevant queries found.\n";
        }
    }
}
