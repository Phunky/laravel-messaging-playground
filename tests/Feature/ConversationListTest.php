<?php

namespace Tests\Feature;

use Database\Factories\ConversationFactory;
use Database\Factories\MessageFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessagingReactions\Reaction;
use Phunky\Models\User;
use Tests\TestCase;

class ConversationListTest extends TestCase
{
    public function test_it_loads_conversations_for_the_authenticated_user(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Hello from Bob');

        Livewire::actingAs($alice)
            ->test('chat.conversation-list')
            ->assertSee('Hello from Bob')
            ->assertSee($bob->name);
    }

    public function test_it_loads_more_conversations_via_cursor_pagination(): void
    {
        $alice = User::factory()->create();
        $messaging = app(MessagingService::class);

        for ($i = 0; $i < 25; $i++) {
            $other = User::factory()->create();
            [$c] = $messaging->findOrCreateConversation($alice, $other);
            $messaging->sendMessage($c, $other, "Message {$i}");
        }

        $component = Livewire::actingAs($alice)->test('chat.conversation-list');

        $this->assertCount(20, $component->get('rows'));
        $this->assertTrue($component->get('hasMore'));

        $component->call('loadMore');

        $this->assertGreaterThanOrEqual(21, count($component->get('rows')));
    }

    public function test_selected_conversation_id_can_be_set_for_row_highlight(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Hi');

        $cid = (int) $conversation->id;

        Livewire::actingAs($alice)
            ->test('chat.conversation-list')
            ->set('selectedConversationId', $cid)
            ->assertSet('selectedConversationId', $cid);
    }

    public function test_refresh_list_preserves_selected_conversation(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Hi');

        Livewire::actingAs($alice)
            ->test('chat.conversation-list')
            ->set('selectedConversationId', (int) $conversation->id)
            ->dispatch('conversation-updated')
            ->assertSet('selectedConversationId', (int) $conversation->id);
    }

    public function test_rows_include_unread_count(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'First');
        $messaging->sendMessage($conversation, $bob, 'Second');
        $messaging->sendMessage($conversation, $bob, 'Third');

        $component = Livewire::actingAs($alice)->test('chat.conversation-list');
        $rows = $component->get('rows');

        $row = collect($rows)->firstWhere('conversation_id', (int) $conversation->id);
        $this->assertNotNull($row);
        $this->assertSame(3, $row['unread_count']);
    }

    public function test_unread_count_decreases_after_mark_all_read(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Unread message');

        $component = Livewire::actingAs($alice)->test('chat.conversation-list');
        $row = collect($component->get('rows'))->firstWhere('conversation_id', (int) $conversation->id);
        $this->assertSame(1, $row['unread_count']);

        $messaging->markAllRead($conversation, $alice);

        $component->dispatch('conversation-updated');
        $row = collect($component->get('rows'))->firstWhere('conversation_id', (int) $conversation->id);
        $this->assertSame(0, $row['unread_count']);
    }

    public function test_conversations_ordered_by_latest_message(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $messaging = app(MessagingService::class);

        [$conversationBob] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversationBob, $bob, 'Older message');

        $this->travel(1)->minutes();

        [$conversationCarol] = $messaging->findOrCreateConversation($alice, $carol);
        $messaging->sendMessage($conversationCarol, $carol, 'Newer message');

        $component = Livewire::actingAs($alice)->test('chat.conversation-list');
        $rows = $component->get('rows');

        $this->assertSame((int) $conversationCarol->id, $rows[0]['conversation_id']);
        $this->assertSame((int) $conversationBob->id, $rows[1]['conversation_id']);
    }

    public function test_reaction_after_last_message_bumps_conversation_to_top(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $messaging = app(MessagingService::class);

        [$conversationBob] = $messaging->findOrCreateConversation($alice, $bob);
        $messageBob = $messaging->sendMessage($conversationBob, $bob, 'Old message');

        $this->travel(1)->minutes();

        [$conversationCarol] = $messaging->findOrCreateConversation($alice, $carol);
        $messaging->sendMessage($conversationCarol, $carol, 'Newer message');

        $this->travel(1)->minutes();

        $participantAlice = $messaging->findParticipant($conversationBob, $alice);
        Reaction::query()->create([
            'message_id' => $messageBob->getKey(),
            'participant_id' => $participantAlice->getKey(),
            'reaction' => '👍',
        ]);

        $component = Livewire::actingAs($alice)->test('chat.conversation-list');
        $rows = $component->get('rows');

        $this->assertSame((int) $conversationBob->id, $rows[0]['conversation_id']);
        $this->assertSame((int) $conversationCarol->id, $rows[1]['conversation_id']);
    }

    public function test_timestamp_shows_time_for_today(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Hi');

        $component = Livewire::actingAs($alice)->test('chat.conversation-list');
        $row = collect($component->get('rows'))->firstWhere('conversation_id', (int) $conversation->id);

        $this->assertMatchesRegularExpression('/^\d{1,2}:\d{2}\s[ap]m$/', $row['formatted_time']);
    }

    public function test_timestamp_shows_yesterday_for_previous_day(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);

        $this->travel(-1)->days();
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Yesterday msg');
        $this->travelBack();

        $component = Livewire::actingAs($alice)->test('chat.conversation-list');
        $row = collect($component->get('rows'))->firstWhere('conversation_id', (int) $conversation->id);

        $this->assertSame('Yesterday', $row['formatted_time']);
    }

    public function test_timestamp_shows_day_name_within_last_week(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);

        $this->travel(-3)->days();
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Few days ago');
        $this->travelBack();

        $component = Livewire::actingAs($alice)->test('chat.conversation-list');
        $row = collect($component->get('rows'))->firstWhere('conversation_id', (int) $conversation->id);

        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $this->assertContains($row['formatted_time'], $dayNames);
    }

    public function test_timestamp_shows_full_date_for_older_messages(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        /** @var Conversation $conversation */
        $conversation = ConversationFactory::new()->direct($alice, $bob)->create();
        MessageFactory::new()->create([
            'conversation_id' => $conversation->getKey(),
            'messageable_type' => $bob->getMorphClass(),
            'messageable_id' => $bob->getKey(),
            'body' => 'Old message',
            'sent_at' => now()->subDays(30),
        ]);

        $component = Livewire::actingAs($alice)->test('chat.conversation-list');
        $row = collect($component->get('rows'))->firstWhere('conversation_id', (int) $conversation->id);

        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4}$#', $row['formatted_time']);
    }

    public function test_last_message_preview_shows_photo_for_image_only_message(): void
    {
        Config::set('messaging.media_disk', 's3');
        Storage::fake('s3');

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        Livewire::actingAs($bob)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->set('attachmentKind', 'image')
            ->set('pendingFiles', [UploadedFile::fake()->image('photo.jpg', 80, 80)])
            ->set('newMessage', '')
            ->call('sendMessage');

        $component = Livewire::actingAs($alice)->test('chat.conversation-list');
        $row = collect($component->get('rows'))->firstWhere('conversation_id', (int) $conversation->id);

        $this->assertNotNull($row);
        $this->assertSame(__('Photo'), $row['subtitle']);
    }
}
