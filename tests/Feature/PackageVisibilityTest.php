<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PackageVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles if they don't exist (using Spatie Permissions)
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $resellerRole = Role::firstOrCreate(['name' => 'reseller']);

        // Create manage_packages permission
        $managePackages = Permission::firstOrCreate(['name' => 'manage_packages']);

        // Only Admin should manage packages now
        $adminRole->givePermissionTo($managePackages);
    }

    public function test_admin_sees_all_packages_in_ui()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $reseller = User::factory()->create();
        $reseller->assignRole('reseller');

        $adminPackage = Package::create([
            'user_id' => $admin->id,
            'name' => 'Admin Package',
            'type' => 'instance',
            'cpu_limit' => 1,
            'ram_limit' => 1,
            'disk_limit' => 10,
        ]);

        $response = $this->actingAs($admin)->get(route('packages.index'));

        $response->assertStatus(200);
        $response->assertSee('Admin Package');
    }

    public function test_reseller_sees_admin_packages_in_ui()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $reseller = User::factory()->create();
        $reseller->assignRole('reseller');

        $adminPackage = Package::create([
            'user_id' => $admin->id,
            'name' => 'Admin Package',
            'type' => 'instance',
            'cpu_limit' => 1,
            'ram_limit' => 1,
            'disk_limit' => 10,
        ]);

        $response = $this->actingAs($reseller)->get(route('packages.index'));

        $response->assertStatus(200);
        $response->assertSee('Admin Package');
        // Ensure buttons are hidden (implementation detail)
        $response->assertDontSee('Create Package');
    }

    public function test_admin_sees_all_packages_in_api()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $adminPackage = Package::create([
            'user_id' => $admin->id,
            'name' => 'Admin Package',
            'type' => 'instance',
            'cpu_limit' => 1,
            'ram_limit' => 1,
            'disk_limit' => 10,
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\CheckApiIp::class);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/integration/packages');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Admin Package']);
    }

    public function test_reseller_sees_admin_packages_in_api()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $reseller = User::factory()->create();
        $reseller->assignRole('reseller');

        $adminPackage = Package::create([
            'user_id' => $admin->id,
            'name' => 'Admin Package',
            'type' => 'instance',
            'cpu_limit' => 1,
            'ram_limit' => 1,
            'disk_limit' => 10,
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\CheckApiIp::class);

        $response = $this->actingAs($reseller, 'sanctum')->getJson('/api/integration/packages');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Admin Package']);
    }

    public function test_reseller_cannot_create_package()
    {
        $reseller = User::factory()->create();
        $reseller->assignRole('reseller');

        $response = $this->actingAs($reseller)->post(route('packages.store'), [
            'name' => 'New Reseller Package',
            'type' => 'instance',
            'cpu_limit' => 2,
            'ram_limit' => 4,
            'disk_limit' => 20,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('packages', [
            'name' => 'New Reseller Package',
        ]);
    }

    public function test_reseller_cannot_update_package()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $reseller = User::factory()->create();
        $reseller->assignRole('reseller');

        $package = Package::create([
            'user_id' => $admin->id,
            'name' => 'Admin Package',
            'type' => 'instance',
            'cpu_limit' => 1,
            'ram_limit' => 1,
            'disk_limit' => 10,
        ]);

        $response = $this->actingAs($reseller)->put(route('packages.update', $package->id), [
            'name' => 'Updated Name',
            'type' => 'instance',
            'cpu_limit' => 2,
            'ram_limit' => 2,
            'disk_limit' => 20,
        ]);

        $response->assertStatus(403);
    }

    public function test_reseller_cannot_delete_package()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $reseller = User::factory()->create();
        $reseller->assignRole('reseller');

        $package = Package::create([
            'user_id' => $admin->id,
            'name' => 'Admin Package',
            'type' => 'instance',
            'cpu_limit' => 1,
            'ram_limit' => 1,
            'disk_limit' => 10,
        ]);

        $response = $this->actingAs($reseller)->delete(route('packages.destroy', $package->id));

        $response->assertStatus(403);
    }
}
