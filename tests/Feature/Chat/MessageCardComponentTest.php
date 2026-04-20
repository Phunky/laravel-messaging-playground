<?php

namespace Tests\Feature\Chat;

use Livewire\Livewire;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\Models\User;
use Phunky\Support\Chat\MessageViewModel;
use Tests\TestCase;

/**
 * Covers the new livewire:chat.message-card SFC boundary. Verifies that:
 * - the computed `viewModel` exposes a hydrated MessageViewModel
 * - startEdit / requestDelete dispatch the expected events for the parent
 *   message-pane to handle
 */
class MessageCardComponentTest extends TestCase
{
    public function test_view_model_computed_returns_hydrated_message_view_model(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messageRow = [
            'id' => 999,
            'body' => 'hello world',
            'sent_at' => '2026-04-20T08:05:00Z',
            'edited_at' => null,
            'sender_id' => (string) $alice->id,
            'sender_name' => $alice->name,
            'is_me' => true,
            'attachments' => [],
        ];

        $component = Livewire::actingAs($alice)->test('chat.message-card', [
            'message' => $messageRow,
            'conversationId' => (int) $conversation->id,
            'isGroup' => false,
        ]);

        $vm = $component->instance()->viewModel;
        $this->assertInstanceOf(MessageViewModel::class, $vm);
        $this->assertSame(999, $vm->id);
        $this->assertSame('hello world', $vm->body);
        $this->assertTrue($vm->isMe);
    }

    public function test_start_edit_dispatches_message_pane_event(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('chat.message-card', [
                'message' => [
                    'id' => 42,
                    'body' => 'hi',
                    'sent_at' => null,
                    'edited_at' => null,
                    'sender_id' => (string) $alice->id,
                    'sender_name' => $alice->name,
                    'is_me' => true,
                    'attachments' => [],
                ],
                'conversationId' => (int) $conversation->id,
                'isGroup' => false,
            ])
            ->call('startEdit')
            ->assertDispatched(
                'message-pane-start-edit',
                fn (string $name, array $params): bool => (int) ($params['messageId'] ?? 0) === 42,
            );
    }

    public function test_request_delete_dispatches_message_pane_event(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($alice)
            ->test('chat.message-card', [
                'message' => [
                    'id' => 77,
                    'body' => 'bye',
                    'sent_at' => null,
                    'edited_at' => null,
                    'sender_id' => (string) $alice->id,
                    'sender_name' => $alice->name,
                    'is_me' => true,
                    'attachments' => [],
                ],
                'conversationId' => (int) $conversation->id,
                'isGroup' => false,
            ])
            ->call('requestDelete')
            ->assertDispatched(
                'message-pane-request-delete',
                fn (string $name, array $params): bool => (int) ($params['messageId'] ?? 0) === 77,
            );
    }
}
