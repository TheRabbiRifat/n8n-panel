<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RequirementCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that an authenticated user can view the requirements page.
     */
    public function test_authenticated_user_can_view_requirements_page(): void
    {
        $user = User::factory()->create();

        // Mock the external IP service
        Http::fake([
            'api.ipify.org' => Http::response('123.123.123.123', 200),
        ]);

        $response = $this->actingAs($user)->get(route('requirements.index'));

        $response->assertStatus(200);
        $response->assertViewIs('requirements.index');
        $response->assertViewHas('checks');

        $checks = $response->viewData('checks');
        $this->assertEquals('123.123.123.123', $checks['server_ip']);
    }

    /**
     * Test that an unauthenticated user is redirected to login.
     */
    public function test_unauthenticated_user_cannot_view_requirements_page(): void
    {
        $response = $this->get(route('requirements.index'));

        $response->assertRedirect(route('login'));
    }
}
