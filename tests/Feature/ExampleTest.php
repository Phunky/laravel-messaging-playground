<?php

namespace Tests\Feature;

use Phunky\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_homepage_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_chat_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk();
    }

    public function test_the_login_page_returns_success(): void
    {
        $this->get(route('login'))->assertOk();
    }
}
