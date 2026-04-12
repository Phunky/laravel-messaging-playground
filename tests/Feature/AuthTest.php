<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Phunky\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_page_renders(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(__('Sign in'), false);
    }

    public function test_login_with_valid_credentials_redirects_home(): void
    {
        $user = User::factory()->create([
            'email' => 'auth-test@example.com',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_invalid_credentials_shows_errors(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_profile_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.profile'))
            ->assertOk()
            ->assertSee(__('Profile'), false);
    }

    public function test_user_can_update_profile_name_and_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        Livewire::actingAs($user)
            ->test('pages::settings.profile')
            ->set('name', 'Updated Name')
            ->set('email', 'updated@example.com')
            ->call('save')
            ->assertSet('saved', true);

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.com', $user->email);
    }

    public function test_profile_update_rejects_duplicate_email(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        User::factory()->create(['email' => 'taken@example.com']);

        Livewire::actingAs($user)
            ->test('pages::settings.profile')
            ->set('email', 'taken@example.com')
            ->call('save')
            ->assertHasErrors('email');

        $user->refresh();
        $this->assertSame('owner@example.com', $user->email);
    }
}
