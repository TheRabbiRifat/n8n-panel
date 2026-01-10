<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Container;
use App\Models\User;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContainerViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_container_show_page_displays_resources_correctly()
    {
        // Create a user
        $user = User::factory()->create();

        // Create a package
        $package = Package::factory()->create([
            'user_id' => $user->id,
            'cpu_limit' => 1.5,
            'ram_limit' => 2.5,
            'disk_limit' => 20.5,
        ]);

        // Create a container associated with the package
        $container = Container::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
        ]);

        // Act: View the container show page
        $response = $this->actingAs($user)->get(route('containers.show', $container));

        // Assert: Check if the values are displayed correctly
        $response->assertStatus(200);
        $response->assertSee('1.5 CPUs');
        $response->assertSee('2.5 GB');
        $response->assertSee('20.5 GB');
    }
}
