<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

class SsoEndpointTest extends TestCase
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

    public function test_admin_can_sso_by_username()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $targetUser = User::factory()->create([
            'username' => 'targetuser',
            'email' => 'target@example.com'
        ]);

        $response = $this->actingAs($admin)
                         ->postJson('/api/integration/users/sso', [
                             'username' => 'targetuser'
                         ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['status', 'redirect_url']);
    }

    public function test_reseller_can_sso_own_user_by_username()
    {
        $reseller = User::factory()->create();
        $reseller->assignRole('reseller');

        $targetUser = User::factory()->create([
            'username' => 'clientuser',
            'reseller_id' => $reseller->id
        ]);

        $response = $this->actingAs($reseller)
                         ->postJson('/api/integration/users/sso', [
                             'username' => 'clientuser'
                         ]);

        $response->assertStatus(200)
                 ->assertJson(['status' => 'success']);
    }

    public function test_reseller_cannot_sso_other_users_by_username()
    {
        $reseller = User::factory()->create();
        $reseller->assignRole('reseller');

        $otherUser = User::factory()->create([
            'username' => 'otheruser',
            'reseller_id' => null
        ]);

        $response = $this->actingAs($reseller)
                         ->postJson('/api/integration/users/sso', [
                             'username' => 'otheruser'
                         ]);

        $response->assertStatus(403);
    }

    public function test_standard_user_cannot_access_sso()
    {
        $user = User::factory()->create();
        // No admin/reseller role

        $targetUser = User::factory()->create(['username' => 'another']);

        $response = $this->actingAs($user)
                         ->postJson('/api/integration/users/sso', [
                             'username' => 'another'
                         ]);

        $response->assertStatus(403);
    }

    public function test_validation_fails_with_email()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $targetUser = User::factory()->create([
            'username' => 'someuser',
            'email' => 'some@example.com'
        ]);

        // Sending email instead of username
        $response = $this->actingAs($admin)
                         ->postJson('/api/integration/users/sso', [
                             'email' => 'some@example.com'
                         ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['username']);
    }
}
