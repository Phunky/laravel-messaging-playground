<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessagingReactions\Reaction;
use Phunky\Models\User;
use Tests\TestCase;

class MessageReactionsTest extends TestCase
{
    public function test_user_can_add_a_reaction_to_a_message(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $message = $messaging->sendMessage($conversation, $alice, 'Hello');

        Livewire::actingAs($alice)
            ->test('chat.message-reactions', [
                'messageId' => (int) $message->id,
                'conversationId' => (int) $conversation->id,
            ])
            ->call('toggle', reaction: '👍')
            ->assertDispatched('conversation-updated', fn (string $name): bool => $name === 'conversation-updated');

        $this->assertSame(1, Reaction::query()->where('message_id', $message->id)->count());
        $this->assertSame('👍', Reaction::query()->where('message_id', $message->id)->value('reaction'));
    }

    public function test_user_can_remove_reaction_by_toggling_the_same_emoji(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $message = $messaging->sendMessage($conversation, $alice, 'Hello');

        $component = Livewire::actingAs($alice)
            ->test('chat.message-reactions', [
                'messageId' => (int) $message->id,
                'conversationId' => (int) $conversation->id,
            ]);

        $component->call('toggle', reaction: '👍');
        $this->assertSame(1, Reaction::query()->where('message_id', $message->id)->count());

        $component->call('toggle', reaction: '👍');
        $this->assertSame(0, Reaction::query()->where('message_id', $message->id)->count());
    }

    public function test_user_can_replace_one_reaction_with_another(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $message = $messaging->sendMessage($conversation, $alice, 'Hello');

        Livewire::actingAs($alice)
            ->test('chat.message-reactions', [
                'messageId' => (int) $message->id,
                'conversationId' => (int) $conversation->id,
            ])
            ->call('toggle', reaction: '👍')
            ->call('toggle', reaction: '❤️');

        $this->assertSame(1, Reaction::query()->where('message_id', $message->id)->count());
        $this->assertSame('❤️', Reaction::query()->where('message_id', $message->id)->value('reaction'));
    }

    public function test_non_participant_cannot_add_a_reaction(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $charlie = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $message = $messaging->sendMessage($conversation, $alice, 'Hello');

        Livewire::actingAs($charlie)
            ->test('chat.message-reactions', [
                'messageId' => (int) $message->id,
                'conversationId' => (int) $conversation->id,
            ])
            ->call('toggle', reaction: '👍');

        $this->assertSame(0, Reaction::query()->where('message_id', $message->id)->count());
    }

    public function test_mobile_open_listener_opens_picker_only_for_matching_message(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $message = $messaging->sendMessage($conversation, $alice, 'Hello');

        Livewire::actingAs($alice)
            ->test('chat.message-reactions', [
                'messageId' => (int) $message->id,
                'conversationId' => (int) $conversation->id,
            ])
            ->call('onOpenMessageReactionPicker', messageId: 999_999)
            ->assertSet('pickerOpen', false);

        Livewire::actingAs($alice)
            ->test('chat.message-reactions', [
                'messageId' => (int) $message->id,
                'conversationId' => (int) $conversation->id,
            ])
            ->call('onOpenMessageReactionPicker', messageId: (int) $message->id)
            ->assertSet('pickerOpen', true);
    }

    public function test_remote_reaction_event_only_busts_cache_for_matching_message(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $message = $messaging->sendMessage($conversation, $alice, 'Hello');

        $component = Livewire::actingAs($alice)
            ->test('chat.message-reactions', [
                'messageId' => (int) $message->id,
                'conversationId' => (int) $conversation->id,
            ]);

        $this->assertSame(0, $component->get('reactionCacheBust'));

        $component->call('onRemoteReactionUpdated', conversationId: (int) $conversation->id, messageId: 999_999);
        $this->assertSame(0, $component->get('reactionCacheBust'));

        $component->call('onRemoteReactionUpdated', conversationId: 999_999, messageId: (int) $message->id);
        $this->assertSame(0, $component->get('reactionCacheBust'));

        $component->call('onRemoteReactionUpdated', conversationId: (int) $conversation->id, messageId: (int) $message->id);
        $this->assertSame(1, $component->get('reactionCacheBust'));
    }
}
