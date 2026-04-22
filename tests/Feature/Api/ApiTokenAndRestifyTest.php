<?php

namespace Tests\Feature\Api;

use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\Models\User;
use Tests\TestCase;

class ApiTokenAndRestifyTest extends TestCase
{
    public function test_token_endpoint_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct')]);

        $this->postJson('/api/auth/token', [
            'email' => $user->email,
            'password' => 'wrong',
            'device_name' => 'phpunit',
        ])->assertUnprocessable();
    }

    public function test_token_endpoint_returns_bearer_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);

        $response = $this->postJson('/api/auth/token', [
            'email' => $user->email,
            'password' => 'secret',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_restify_requires_authentication(): void
    {
        $this->postJson('/api/restify/conversations/actions?action=conversation-inbox-action')
            ->assertUnauthorized();
    }

    public function test_inbox_action_returns_rows_for_participant(): void
    {
        $alice = User::factory()->create(['password' => bcrypt('secret')]);
        $bob = User::factory()->create();
        app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        $token = $this->postJson('/api/auth/token', [
            'email' => $alice->email,
            'password' => 'secret',
            'device_name' => 'phpunit',
        ])->json('token');

        $response = $this->postJson(
            '/api/restify/conversations/actions?action=conversation-inbox-action',
            [],
            ['Authorization' => 'Bearer '.$token]
        );

        $response->assertOk()
            ->assertJsonStructure(['rows', 'next_cursor', 'has_more']);

        $this->assertNotEmpty($response->json('rows'));
    }

    public function test_thread_messages_action_returns_messages(): void
    {
        $alice = User::factory()->create(['password' => bcrypt('secret')]);
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $alice, 'hello api');

        $token = $this->postJson('/api/auth/token', [
            'email' => $alice->email,
            'password' => 'secret',
            'device_name' => 'phpunit',
        ])->json('token');

        $response = $this->postJson(
            '/api/restify/messages/actions?action=thread-messages-action',
            ['conversation_id' => (int) $conversation->getKey()],
            ['Authorization' => 'Bearer '.$token]
        );

        $response->assertOk()
            ->assertJsonStructure(['messages', 'next_cursor', 'has_more']);

        $this->assertNotEmpty($response->json('messages'));
        $this->assertSame('hello api', $response->json('messages.0.body'));
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);

        $token = $this->postJson('/api/auth/token', [
            'email' => $user->email,
            'password' => 'secret',
            'device_name' => 'phpunit',
        ])->json('token');

        $this->assertSame(1, $user->fresh()->tokens()->count());

        $this->postJson('/api/auth/logout', [], ['Authorization' => 'Bearer '.$token])
            ->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_revoked_bearer_token_cannot_access_restify(): void
    {
        $this->postJson(
            '/api/restify/conversations/actions?action=conversation-inbox-action',
            [],
            ['Authorization' => 'Bearer 1|invalidPlainTextTokenThatDoesNotExist']
        )->assertUnauthorized();
    }
}
