<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\Models\User;
use Tests\TestCase;

class ChatPageMobileStackTest extends TestCase
{
    public function test_select_conversation_loads_thread_in_message_pane(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Unique thread body for pane');

        Livewire::actingAs($alice)
            ->test('pages::chat')
            ->call('selectConversation', conversationId: (int) $conversation->id)
            ->assertSee('Unique thread body for pane');
    }

    public function test_mobile_stack_starts_at_list(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::chat')
            ->assertSet('mobileStack', 'list');
    }

    public function test_conversation_selected_sets_mobile_stack_to_messages(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('pages::chat')
            ->call('selectConversation', conversationId: (int) $conversation->id)
            ->assertSet('mobileStack', 'messages');
    }

    public function test_chat_mobile_back_returns_stack_to_list(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('pages::chat')
            ->call('selectConversation', conversationId: (int) $conversation->id)
            ->assertSet('mobileStack', 'messages')
            ->dispatch('chat-mobile-back')
            ->assertSet('mobileStack', 'list');
    }
}
