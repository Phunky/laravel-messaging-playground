<?php

namespace Tests\Feature\View;

use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\Models\User;
use Tests\TestCase;

/**
 * Covers the ChatLayoutComposer binding that replaced the inline `@php` block
 * previously living in `layouts/app.blade.php`. It confirms that guest requests
 * get null/empty values and authenticated users receive their id, name, and a
 * comma-separated list of their conversation ids.
 */
class ChatLayoutComposerTest extends TestCase
{
    public function test_guest_context_receives_null_identifiers(): void
    {
        $view = view('layouts.app')->with('slot', '');

        $rendered = $view->render();

        $this->assertStringNotContainsString('name="chat-user-id"', $rendered);
        $this->assertStringNotContainsString('name="chat-user-name"', $rendered);
        $this->assertStringNotContainsString('name="chat-conversation-ids"', $rendered);
    }

    public function test_authenticated_user_receives_meta_tags_with_conversation_ids(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();

        $messaging = app(MessagingService::class);
        [$convoOne] = $messaging->findOrCreateConversation($alice, $bob);
        [$convoTwo] = $messaging->findOrCreateConversation($alice, $carol);

        $this->actingAs($alice);

        $rendered = view('layouts.app')->with('slot', '')->render();

        $this->assertStringContainsString('name="chat-user-id"', $rendered);
        $this->assertStringContainsString('content="'.$alice->id.'"', $rendered);
        $this->assertStringContainsString('name="chat-user-name"', $rendered);
        $this->assertStringContainsString($alice->name, $rendered);
        $this->assertStringContainsString('name="chat-conversation-ids"', $rendered);
        $this->assertStringContainsString((string) $convoOne->id, $rendered);
        $this->assertStringContainsString((string) $convoTwo->id, $rendered);
    }

    public function test_authenticated_user_without_conversations_gets_empty_ids_string(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $rendered = view('layouts.app')->with('slot', '')->render();

        $this->assertStringContainsString('name="chat-conversation-ids" content=""', $rendered);
    }
}
