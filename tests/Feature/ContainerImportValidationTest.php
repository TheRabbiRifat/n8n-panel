<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class ContainerImportValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles if necessary
        if (!Role::where('name', 'admin')->exists()) {
            Role::create(['name' => 'admin']);
        }
    }

    public function test_import_with_invalid_name_fails_validation()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $package = Package::factory()->create([
            'user_id' => $admin->id
        ]);

        // Attempt import with name containing spaces
        $response = $this->actingAs($admin)->post(route('containers.import'), [
            'docker_id' => 'existing_docker_id',
            'name' => 'invalid name', // Spaces not allowed in alpha_dash
            'user_id' => $admin->id,
            'package_id' => $package->id,
            'port' => 5678,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_import_with_path_traversal_fails_validation()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $package = Package::factory()->create([
            'user_id' => $admin->id
        ]);

        // Attempt import with path traversal characters
        $response = $this->actingAs($admin)->post(route('containers.import'), [
            'docker_id' => 'existing_docker_id_2',
            'name' => '../../etc/passwd',
            'user_id' => $admin->id,
            'package_id' => $package->id,
            'port' => 5679,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_import_with_too_long_name_fails_validation()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $package = Package::factory()->create([
            'user_id' => $admin->id
        ]);

        // Max length 64
        $name = str_repeat('a', 65);

        $response = $this->actingAs($admin)->post(route('containers.import'), [
            'docker_id' => 'existing_docker_id_3',
            'name' => $name,
            'user_id' => $admin->id,
            'package_id' => $package->id,
            'port' => 5680,
        ]);

        $response->assertSessionHasErrors(['name']);
    }
}
