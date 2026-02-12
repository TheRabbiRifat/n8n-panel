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

        // Assign permission to roles
        $adminRole->givePermissionTo($managePackages);
        $resellerRole->givePermissionTo($managePackages);
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

        $resellerPackage = Package::create([
            'user_id' => $reseller->id,
            'name' => 'Reseller Package',
            'type' => 'instance',
            'cpu_limit' => 1,
            'ram_limit' => 1,
            'disk_limit' => 10,
        ]);

        $response = $this->actingAs($admin)->get(route('packages.index'));

        $response->assertStatus(200);
        $response->assertSee('Admin Package');
        $response->assertSee('Reseller Package');
    }

    public function test_reseller_sees_only_own_packages_in_ui()
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

        $resellerPackage = Package::create([
            'user_id' => $reseller->id,
            'name' => 'Reseller Package',
            'type' => 'instance',
            'cpu_limit' => 1,
            'ram_limit' => 1,
            'disk_limit' => 10,
        ]);

        $response = $this->actingAs($reseller)->get(route('packages.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Admin Package');
        $response->assertSee('Reseller Package');
    }

    public function test_admin_sees_only_own_packages_in_api()
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

        $resellerPackage = Package::create([
            'user_id' => $reseller->id,
            'name' => 'Reseller Package',
            'type' => 'instance',
            'cpu_limit' => 1,
            'ram_limit' => 1,
            'disk_limit' => 10,
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\CheckApiIp::class);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/integration/packages');

        $response->assertStatus(200);

        // Assert JSON structure and content
        $response->assertJsonFragment(['name' => 'Admin Package']);
        $response->assertJsonMissing(['name' => 'Reseller Package']);
    }

    public function test_reseller_sees_only_own_packages_in_api()
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

        $resellerPackage = Package::create([
            'user_id' => $reseller->id,
            'name' => 'Reseller Package',
            'type' => 'instance',
            'cpu_limit' => 1,
            'ram_limit' => 1,
            'disk_limit' => 10,
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\CheckApiIp::class);

        $response = $this->actingAs($reseller, 'sanctum')->getJson('/api/integration/packages');

        $response->assertStatus(200);

        $response->assertJsonFragment(['name' => 'Reseller Package']);
        $response->assertJsonMissing(['name' => 'Admin Package']);
    }

    public function test_reseller_can_create_package()
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

        $response->assertRedirect(route('packages.index'));
        $this->assertDatabaseHas('packages', [
            'name' => 'New Reseller Package',
            'user_id' => $reseller->id,
        ]);
    }

    public function test_reseller_can_update_own_package()
    {
        $reseller = User::factory()->create();
        $reseller->assignRole('reseller');

        $package = Package::create([
            'user_id' => $reseller->id,
            'name' => 'Old Name',
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

        $response->assertRedirect(route('packages.index'));
        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'name' => 'Updated Name',
            'cpu_limit' => 2,
        ]);
    }

    public function test_reseller_cannot_update_others_package()
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
            'name' => 'Hacked Name',
            'type' => 'instance',
            'cpu_limit' => 2,
            'ram_limit' => 2,
            'disk_limit' => 20,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'name' => 'Admin Package',
        ]);
    }

    public function test_reseller_can_delete_own_package()
    {
        $reseller = User::factory()->create();
        $reseller->assignRole('reseller');

        $package = Package::create([
            'user_id' => $reseller->id,
            'name' => 'To Be Deleted',
            'type' => 'instance',
            'cpu_limit' => 1,
            'ram_limit' => 1,
            'disk_limit' => 10,
        ]);

        $response = $this->actingAs($reseller)->delete(route('packages.destroy', $package->id));

        $response->assertRedirect(); // Usually back()
        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
    }

    public function test_reseller_cannot_delete_others_package()
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
        $this->assertDatabaseHas('packages', ['id' => $package->id]);
    }
}
