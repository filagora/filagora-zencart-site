<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_authenticated_user_can_reach_register_page_and_is_logged_out(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/dashboard/register');

        $response->assertOk();
        $response->assertSee('Register');
        $this->assertGuest();
    }
}
