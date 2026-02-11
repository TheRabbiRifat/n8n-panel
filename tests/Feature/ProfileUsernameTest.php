<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUsernameTest extends TestCase
{
    use RefreshDatabase;

    public function test_username_is_visible_on_profile_edit_page()
    {
        $user = User::factory()->create([
            'username' => 'testuser123'
        ]);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertStatus(200);
        $response->assertSee('testuser123');
        $response->assertSee('Usernames cannot be changed');
    }

    public function test_username_is_visible_on_api_tokens_page()
    {
        $user = User::factory()->create([
            'username' => 'apiuser456'
        ]);

        $response = $this->actingAs($user)->get(route('api-tokens.index'));

        $response->assertStatus(200);
        $response->assertSee('apiuser456');
    }
}
