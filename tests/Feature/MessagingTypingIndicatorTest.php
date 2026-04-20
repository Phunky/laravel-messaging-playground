<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\Models\User;
use Tests\TestCase;

class MessagingTypingIndicatorTest extends TestCase
{
    public function test_message_pane_updates_typing_users_for_the_open_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$conversation] = app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->dispatch('messaging-typing-updated', conversationId: (int) $conversation->id, typingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->assertSet('typingUsers', [
                ['id' => (int) $bob->id, 'name' => $bob->name],
            ])
            ->assertSee($bob->name);
    }

    public function test_message_pane_ignores_typing_for_other_conversations(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$open] = $messaging->findOrCreateConversation($alice, $bob);
        [$other] = $messaging->findOrCreateConversation($alice, $carol);

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $open->id)
            ->dispatch('messaging-typing-updated', conversationId: (int) $other->id, typingUsers: [
                ['id' => $carol->id, 'name' => $carol->name],
            ])
            ->assertSet('typingUsers', []);
    }

    public function test_message_pane_filters_self_out_of_typing_users(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$conversation] = app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->dispatch('messaging-typing-updated', conversationId: (int) $conversation->id, typingUsers: [
                ['id' => $alice->id, 'name' => $alice->name],
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->assertSet('typingUsers', [
                ['id' => (int) $bob->id, 'name' => $bob->name],
            ]);
    }

    public function test_message_pane_clears_typing_users_on_conversation_switch(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$first] = $messaging->findOrCreateConversation($alice, $bob);
        [$second] = $messaging->findOrCreateConversation($alice, $carol);

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $first->id)
            ->dispatch('messaging-typing-updated', conversationId: (int) $first->id, typingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->assertSet('typingUsers', [
                ['id' => (int) $bob->id, 'name' => $bob->name],
            ])
            ->set('conversationId', (int) $second->id)
            ->assertSet('typingUsers', []);
    }

    public function test_conversation_list_stores_typing_names_keyed_by_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Hi Alice');

        Livewire::actingAs($alice)
            ->test('chat.conversation-list')
            ->dispatch('messaging-typing-updated', conversationId: (int) $conversation->id, typingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->assertSet('typingByConversation', [
                (int) $conversation->id => [$bob->name],
            ])
            ->assertSee(__(':name is typing…', ['name' => $bob->name]));
    }

    public function test_conversation_list_clears_typing_when_list_empties(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$conversation] = app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('chat.conversation-list')
            ->dispatch('messaging-typing-updated', conversationId: (int) $conversation->id, typingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->assertSet('typingByConversation', [
                (int) $conversation->id => [$bob->name],
            ])
            ->dispatch('messaging-typing-updated', conversationId: (int) $conversation->id, typingUsers: [])
            ->assertSet('typingByConversation', []);
    }

    public function test_conversation_list_tracks_online_participant_ids(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Hi');

        Livewire::actingAs($alice)
            ->test('chat.conversation-list')
            ->dispatch('messaging-presence-updated', conversationId: (int) $conversation->id, onlineUserIds: [
                $alice->id,
                $bob->id,
            ])
            ->assertSet('onlineUserIdsByConversation', [
                (int) $conversation->id => [(int) $alice->id, (int) $bob->id],
            ]);
    }

    public function test_conversation_list_clears_presence_entry_when_channel_empties(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$conversation] = app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('chat.conversation-list')
            ->dispatch('messaging-presence-updated', conversationId: (int) $conversation->id, onlineUserIds: [
                $bob->id,
            ])
            ->assertSet('onlineUserIdsByConversation', [
                (int) $conversation->id => [(int) $bob->id],
            ])
            ->dispatch('messaging-presence-updated', conversationId: (int) $conversation->id, onlineUserIds: [])
            ->assertSet('onlineUserIdsByConversation', []);
    }

    public function test_message_pane_updates_recording_users_for_the_open_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$conversation] = app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->dispatch('messaging-recording-updated', conversationId: (int) $conversation->id, recordingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->assertSet('recordingUsers', [
                ['id' => (int) $bob->id, 'name' => $bob->name],
            ]);
    }

    public function test_message_thread_renders_recording_card_for_the_open_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$conversation] = app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('chat.message-thread', ['conversationId' => (int) $conversation->id, 'isActive' => true])
            ->dispatch('messaging-recording-updated', conversationId: (int) $conversation->id, recordingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->assertSet('recordingUsers', [
                ['id' => (int) $bob->id, 'name' => $bob->name],
            ])
            ->assertSee(__(':name is recording a voice note…', ['name' => $bob->name]));
    }

    public function test_message_thread_renders_typing_card_for_the_open_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$conversation] = app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('chat.message-thread', ['conversationId' => (int) $conversation->id, 'isActive' => true])
            ->dispatch('messaging-typing-updated', conversationId: (int) $conversation->id, typingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->assertSet('typingUsers', [
                ['id' => (int) $bob->id, 'name' => $bob->name],
            ])
            ->assertSee(__(':name is typing…', ['name' => $bob->name]));
    }

    public function test_message_thread_recording_card_takes_precedence_over_typing(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$conversation] = app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('chat.message-thread', ['conversationId' => (int) $conversation->id, 'isActive' => true])
            ->dispatch('messaging-typing-updated', conversationId: (int) $conversation->id, typingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->dispatch('messaging-recording-updated', conversationId: (int) $conversation->id, recordingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->assertSee(__(':name is recording a voice note…', ['name' => $bob->name]))
            ->assertDontSee(__(':name is typing…', ['name' => $bob->name]));
    }

    public function test_message_pane_ignores_recording_for_other_conversations(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$open] = $messaging->findOrCreateConversation($alice, $bob);
        [$other] = $messaging->findOrCreateConversation($alice, $carol);

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $open->id)
            ->dispatch('messaging-recording-updated', conversationId: (int) $other->id, recordingUsers: [
                ['id' => $carol->id, 'name' => $carol->name],
            ])
            ->assertSet('recordingUsers', []);
    }

    public function test_message_pane_filters_self_out_of_recording_users(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$conversation] = app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->dispatch('messaging-recording-updated', conversationId: (int) $conversation->id, recordingUsers: [
                ['id' => $alice->id, 'name' => $alice->name],
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->assertSet('recordingUsers', [
                ['id' => (int) $bob->id, 'name' => $bob->name],
            ]);
    }

    public function test_message_pane_clears_recording_users_on_conversation_switch(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$first] = $messaging->findOrCreateConversation($alice, $bob);
        [$second] = $messaging->findOrCreateConversation($alice, $carol);

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $first->id)
            ->dispatch('messaging-recording-updated', conversationId: (int) $first->id, recordingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->assertSet('recordingUsers', [
                ['id' => (int) $bob->id, 'name' => $bob->name],
            ])
            ->set('conversationId', (int) $second->id)
            ->assertSet('recordingUsers', []);
    }

    public function test_conversation_list_stores_recording_names_keyed_by_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Hi Alice');

        Livewire::actingAs($alice)
            ->test('chat.conversation-list')
            ->dispatch('messaging-recording-updated', conversationId: (int) $conversation->id, recordingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->assertSet('recordingByConversation', [
                (int) $conversation->id => [$bob->name],
            ])
            ->assertSee(__(':name is recording a voice note…', ['name' => $bob->name]));
    }

    public function test_conversation_list_recording_takes_priority_over_typing(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Hi Alice');

        $component = Livewire::actingAs($alice)
            ->test('chat.conversation-list')
            ->dispatch('messaging-typing-updated', conversationId: (int) $conversation->id, typingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->dispatch('messaging-recording-updated', conversationId: (int) $conversation->id, recordingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ]);

        $component
            ->assertSee(__(':name is recording a voice note…', ['name' => $bob->name]))
            ->assertDontSee(__(':name is typing…', ['name' => $bob->name]));
    }

    public function test_conversation_list_clears_recording_when_list_empties(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$conversation] = app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('chat.conversation-list')
            ->dispatch('messaging-recording-updated', conversationId: (int) $conversation->id, recordingUsers: [
                ['id' => $bob->id, 'name' => $bob->name],
            ])
            ->assertSet('recordingByConversation', [
                (int) $conversation->id => [$bob->name],
            ])
            ->dispatch('messaging-recording-updated', conversationId: (int) $conversation->id, recordingUsers: [])
            ->assertSet('recordingByConversation', []);
    }
}
