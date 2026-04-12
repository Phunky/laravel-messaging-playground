<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessagingAttachments\Attachment;
use Phunky\LaravelMessagingGroups\GroupService;
use Phunky\Models\User;
use Tests\TestCase;

class MessagePaneTest extends TestCase
{
    public function test_user_can_edit_own_message_from_message_pane(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $message = $messaging->sendMessage($conversation, $alice, 'Original');

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->call('startEdit', messageId: $message->id)
            ->assertSet('editingMessageId', $message->id)
            ->set('editMessageBody', 'Updated body')
            ->call('saveEdit')
            ->assertSet('editingMessageId', null)
            ->assertDispatched(
                'chat-message-replaced',
                fn (string $name, array $params): bool => ($params['message']['body'] ?? null) === 'Updated body'
                    && (int) ($params['conversationId'] ?? 0) === (int) $conversation->id,
            );

        $message->refresh();
        $this->assertSame('Updated body', $message->body);
        $this->assertNotNull($message->edited_at);
    }

    public function test_user_can_delete_own_message_from_message_pane(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $message = $messaging->sendMessage($conversation, $alice, 'Bye');

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->call('deleteMessage', messageId: $message->id)
            ->assertDispatched(
                'chat-message-removed',
                fn (string $name, array $params): bool => (int) ($params['conversationId'] ?? 0) === (int) $conversation->id
                    && (int) ($params['messageId'] ?? 0) === (int) $message->id,
            );

        $this->assertSoftDeleted($message);
    }

    public function test_user_can_send_message_from_message_pane(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Existing');

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->set('newMessage', 'Just sent')
            ->call('sendMessage')
            ->assertDispatched(
                'chat-message-appended',
                fn (string $name, array $params): bool => ($params['message']['body'] ?? null) === 'Just sent'
                    && (int) ($params['conversationId'] ?? 0) === (int) $conversation->id,
            );
    }

    public function test_load_older_prepends_previous_messages(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        for ($i = 1; $i <= 55; $i++) {
            $messaging->sendMessage($conversation, $bob, "Line {$i}");
        }

        $component = Livewire::actingAs($alice)
            ->test('chat.message-thread', [
                'conversationId' => $conversation->id,
                'isActive' => true,
            ]);

        $this->assertCount(50, $component->get('messagesViewport'));

        $component->call('loadOlder');

        $this->assertCount(55, $component->get('messagesViewport'));
    }

    public function test_user_cannot_start_edit_another_users_message(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $message = $messaging->sendMessage($conversation, $bob, 'From Bob');

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->call('startEdit', messageId: $message->id)
            ->assertSet('editingMessageId', null)
            ->assertSet('editModalOpen', false);
    }

    public function test_switching_conversation_keeps_warmed_threads_and_updates_active_selection(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversationOne] = $messaging->findOrCreateConversation($alice, $bob);
        [$conversationTwo] = $messaging->findOrCreateConversation($alice, $carol);
        $messaging->sendMessage($conversationOne, $alice, 'First thread');
        $messaging->sendMessage($conversationTwo, $alice, 'Second thread');

        $component = Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversationOne->id);

        $this->assertSame($conversationOne->id, $component->get('conversationId'));
        $this->assertContains($conversationOne->id, $component->get('warmConversationIds'));

        $component->set('conversationId', (int) $conversationTwo->id);

        $this->assertSame($conversationTwo->id, $component->get('conversationId'));
        $this->assertContains($conversationOne->id, $component->get('warmConversationIds'));
        $this->assertContains($conversationTwo->id, $component->get('warmConversationIds'));
    }

    public function test_direct_conversation_is_not_marked_as_group(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Hi');

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->assertSet('isGroup', false)
            ->assertSet('headerTitle', $bob->name);
    }

    public function test_group_conversation_is_marked_as_group(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        $groups = app(GroupService::class);

        $group = $groups->create($alice, 'Team Alpha', null, []);
        $groups->invite($group, $alice, $bob);
        $messaging->sendMessage($group->conversation, $bob, 'Hello team');

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $group->conversation_id)
            ->assertSet('isGroup', true)
            ->assertSet('headerTitle', 'Team Alpha');
    }

    public function test_chat_page_renders_centered_messages_pane_width(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('max-w-4xl', false)
            ->assertSee('items-center', false);
    }

    public function test_selecting_conversation_marks_all_messages_as_read(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Unread 1');
        $messaging->sendMessage($conversation, $bob, 'Unread 2');

        $this->assertSame(2, $messaging->unreadCount($conversation, $alice));

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id);

        $this->assertSame(0, $messaging->unreadCount($conversation, $alice));
    }

    public function test_remote_message_broadcast_event_marks_conversation_read_when_pane_is_open(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'First');

        $component = Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id);

        $this->assertSame(0, $messaging->unreadCount($conversation, $alice));

        $incoming = $messaging->sendMessage($conversation, $bob, 'While you watch');

        $this->assertSame(1, $messaging->unreadCount($conversation, $alice));

        $component->dispatch(
            'messaging-remote-message-sent',
            conversationId: (int) $conversation->id,
            messageId: (int) $incoming->id,
        );

        $this->assertSame(0, $messaging->unreadCount($conversation, $alice));
    }

    public function test_sending_message_marks_incoming_unread_as_read(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $messaging->sendMessage($conversation, $bob, 'Initial');

        $component = Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id);

        $this->assertSame(0, $messaging->unreadCount($conversation, $alice));

        $messaging->sendMessage($conversation, $bob, 'While pane open');

        $this->assertSame(1, $messaging->unreadCount($conversation, $alice));

        $component->set('newMessage', 'My reply')->call('sendMessage');

        $this->assertSame(0, $messaging->unreadCount($conversation, $alice));
    }

    public function test_unread_count_reflects_only_unread_messages_for_participant(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        $messaging->sendMessage($conversation, $alice, 'My own message');
        $messaging->sendMessage($conversation, $bob, 'From Bob');

        $this->assertSame(2, $messaging->unreadCount($conversation, $alice));

        $messaging->markAllRead($conversation, $alice);

        $this->assertSame(0, $messaging->unreadCount($conversation, $alice));

        $messaging->sendMessage($conversation, $bob, 'Another from Bob');
        $this->assertSame(1, $messaging->unreadCount($conversation, $alice));
    }

    public function test_user_can_send_message_with_image_attachment(): void
    {
        Config::set('messaging.media_disk', 's3');
        Storage::fake('s3');

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        $file = UploadedFile::fake()->image('photo.jpg', 400, 300);

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->set('attachmentKind', 'image')
            ->set('pendingFiles', [$file])
            ->set('newMessage', '')
            ->call('sendMessage')
            ->assertDispatched(
                'chat-message-appended',
                fn (string $name, array $params): bool => count($params['message']['attachments'] ?? []) === 1
                    && ($params['message']['attachments'][0]['type'] ?? null) === 'image',
            );

        $this->assertSame(1, Attachment::query()->count());

        $row = Attachment::query()->firstOrFail();
        Storage::disk('s3')->assertExists($row->path);
    }

    public function test_user_can_send_message_with_video_attachment(): void
    {
        Config::set('messaging.media_disk', 's3');
        Storage::fake('s3');

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        $file = UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4');

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->set('attachmentKind', 'video')
            ->set('pendingFiles', [$file])
            ->set('newMessage', '')
            ->call('sendMessage')
            ->assertDispatched(
                'chat-message-appended',
                fn (string $name, array $params): bool => ($params['message']['attachments'][0]['type'] ?? null) === 'video'
                    && ($params['message']['attachments'][0]['mime_type'] ?? null) === 'video/mp4',
            );

        $this->assertSame(1, Attachment::query()->count());

        $row = Attachment::query()->firstOrFail();
        $this->assertSame('video', $row->type);
        Storage::disk('s3')->assertExists($row->path);
    }

    public function test_video_attachment_rejects_more_than_one_file(): void
    {
        Config::set('messaging.media_disk', 's3');
        Storage::fake('s3');

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        $first = UploadedFile::fake()->create('a.mp4', 50, 'video/mp4');
        $second = UploadedFile::fake()->create('b.mp4', 50, 'video/mp4');

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->set('attachmentKind', 'video')
            ->set('pendingFiles', [$first, $second])
            ->set('newMessage', '')
            ->call('sendMessage')
            ->assertHasErrors(['pendingFiles']);
    }

    public function test_user_can_send_message_with_document_attachment(): void
    {
        Config::set('messaging.media_disk', 's3');
        Storage::fake('s3');

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        $file = UploadedFile::fake()->create('report.pdf', 500, 'application/pdf');
        $expectedSize = $file->getSize();

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->set('attachmentKind', 'document')
            ->set('pendingFiles', [$file])
            ->set('newMessage', '')
            ->call('sendMessage')
            ->assertDispatched(
                'chat-message-appended',
                fn (string $name, array $params): bool => ($params['message']['attachments'][0]['type'] ?? null) === 'document'
                    && ($params['message']['attachments'][0]['size'] ?? null) === $expectedSize,
            );

        $this->assertSame(1, Attachment::query()->count());

        $row = Attachment::query()->firstOrFail();
        $this->assertSame('document', $row->type);
        Storage::disk('s3')->assertExists($row->path);
        $this->assertSame($expectedSize, $row->size ?? null);
    }

    public function test_user_can_send_message_with_voice_note_attachment(): void
    {
        Config::set('messaging.media_disk', 's3');
        Storage::fake('s3');

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        $file = UploadedFile::fake()->create('voice-note.webm', 400, 'audio/webm');
        $expectedSize = $file->getSize();

        Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id)
            ->set('attachmentKind', 'voice_note')
            ->set('pendingFiles', [$file])
            ->set('newMessage', '')
            ->call('sendMessage')
            ->assertHasNoErrors();

        $this->assertSame(1, Attachment::query()->count());

        $row = Attachment::query()->firstOrFail();
        $this->assertSame('voice_note', $row->type);
        Storage::disk('s3')->assertExists($row->path);
        $this->assertSame($expectedSize, $row->size ?? null);
    }

    public function test_prepare_upload_clears_staged_files_when_switching_attachment_kind(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        $image = UploadedFile::fake()->image('photo.jpg', 100, 100);

        $component = Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id);

        $component
            ->set('attachmentKind', 'image')
            ->set('pendingFiles', [$image]);

        $this->assertCount(1, $component->get('pendingFiles'));

        $component
            ->call('prepareUpload', kind: 'document')
            ->assertSet('pendingFiles', [])
            ->assertSet('attachmentKind', 'document');
    }

    public function test_participant_opens_media_viewer_with_conversation_media_ordered_chronologically(): void
    {
        Config::set('messaging.media_disk', 's3');
        Storage::fake('s3');

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        $component = Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id);

        $component
            ->set('attachmentKind', 'image')
            ->set('pendingFiles', [UploadedFile::fake()->image('first.jpg', 80, 80)])
            ->set('newMessage', '')
            ->call('sendMessage');

        $firstAttachmentId = (int) Attachment::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->value('id');

        $component
            ->set('attachmentKind', 'image')
            ->set('pendingFiles', [UploadedFile::fake()->image('second.jpg', 80, 80)])
            ->set('newMessage', '')
            ->call('sendMessage');

        $secondAttachmentId = (int) Attachment::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->value('id');

        $component->call('openMediaViewer', attachmentId: $firstAttachmentId);

        $component->assertSet('mediaViewerOpen', true);
        $items = $component->get('mediaViewerItems');
        $this->assertCount(2, $items);
        $this->assertSame($firstAttachmentId, $items[0]['id']);
        $this->assertSame($secondAttachmentId, $items[1]['id']);
        $this->assertSame('image', $items[0]['type']);
        $this->assertSame(0, $component->get('mediaViewerIndex'));

        $component->call('mediaViewerGo', delta: 1);
        $this->assertSame(1, $component->get('mediaViewerIndex'));

        $component->call('closeMediaViewer');
        $this->assertFalse($component->get('mediaViewerOpen'));
    }

    public function test_open_media_viewer_limits_items_to_message_when_message_id_passed(): void
    {
        Config::set('messaging.media_disk', 's3');
        Storage::fake('s3');

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        $component = Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id);

        $component
            ->set('attachmentKind', 'image')
            ->set('pendingFiles', [UploadedFile::fake()->image('first.jpg', 80, 80)])
            ->set('newMessage', '')
            ->call('sendMessage');

        $firstMessageId = (int) Attachment::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->value('message_id');
        $firstAttachmentId = (int) Attachment::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->value('id');

        $component
            ->set('attachmentKind', 'image')
            ->set('pendingFiles', [UploadedFile::fake()->image('second.jpg', 80, 80)])
            ->set('newMessage', '')
            ->call('sendMessage');

        $secondAttachmentId = (int) Attachment::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->value('id');

        $component->call('openMediaViewer', attachmentId: $firstAttachmentId, messageId: $firstMessageId);

        $component->assertSet('mediaViewerOpen', true);
        $items = $component->get('mediaViewerItems');
        $this->assertCount(1, $items);
        $this->assertSame($firstAttachmentId, $items[0]['id']);

        $component->call('closeMediaViewer');

        $component->call('openMediaViewer', attachmentId: $firstAttachmentId);
        $this->assertCount(2, $component->get('mediaViewerItems'));
        $this->assertSame($firstAttachmentId, $component->get('mediaViewerItems')[0]['id']);
        $this->assertSame($secondAttachmentId, $component->get('mediaViewerItems')[1]['id']);
    }

    public function test_non_participant_cannot_populate_media_viewer(): void
    {
        Config::set('messaging.media_disk', 's3');
        Storage::fake('s3');

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        $component = Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id);

        $component
            ->set('attachmentKind', 'image')
            ->set('pendingFiles', [UploadedFile::fake()->image('solo.jpg', 80, 80)])
            ->set('newMessage', '')
            ->call('sendMessage');

        $attachmentId = (int) Attachment::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->value('id');

        Livewire::actingAs($carol)
            ->test('chat.message-pane')
            ->set('conversationId', $conversation->id)
            ->call('openMediaViewer', attachmentId: $attachmentId)
            ->assertSet('mediaViewerOpen', false)
            ->assertSet('mediaViewerItems', []);
    }

    public function test_header_media_opens_viewer_at_first_chronological_item(): void
    {
        Config::set('messaging.media_disk', 's3');
        Storage::fake('s3');

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        $component = Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $conversation->id);

        $component->assertSet('conversationHasMedia', false);

        $component
            ->set('attachmentKind', 'image')
            ->set('pendingFiles', [UploadedFile::fake()->image('first.jpg', 80, 80)])
            ->set('newMessage', '')
            ->call('sendMessage');

        $firstAttachmentId = (int) Attachment::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->value('id');

        $component
            ->set('attachmentKind', 'image')
            ->set('pendingFiles', [UploadedFile::fake()->image('second.jpg', 80, 80)])
            ->set('newMessage', '')
            ->call('sendMessage');

        $this->assertTrue($component->get('conversationHasMedia'));

        $component->call('openConversationMediaViewer');

        $component->assertSet('mediaViewerOpen', true);
        $this->assertSame(0, $component->get('mediaViewerIndex'));
        $items = $component->get('mediaViewerItems');
        $this->assertSame($firstAttachmentId, $items[0]['id']);
    }

    public function test_switching_between_conversations_keeps_warm_list_and_each_thread_renders_its_messages(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$convA] = $messaging->findOrCreateConversation($alice, $bob);
        [$convB] = $messaging->findOrCreateConversation($alice, $carol);

        $messaging->sendMessage($convA, $bob, 'Only in A');
        $messaging->sendMessage($convB, $carol, 'Only in B');

        $pane = Livewire::actingAs($alice)
            ->test('chat.message-pane')
            ->set('conversationId', (int) $convA->id);

        $this->assertSame($convA->id, $pane->get('conversationId'));
        $this->assertContains($convA->id, $pane->get('warmConversationIds'));

        Livewire::actingAs($alice)
            ->test('chat.message-thread', ['conversationId' => $convA->id, 'isActive' => true])
            ->assertSee('Only in A');

        $pane->set('conversationId', (int) $convB->id);
        $this->assertSame($convB->id, $pane->get('conversationId'));
        $this->assertContains($convA->id, $pane->get('warmConversationIds'));

        Livewire::actingAs($alice)
            ->test('chat.message-thread', ['conversationId' => $convB->id, 'isActive' => true])
            ->assertSee('Only in B');

        $pane->set('conversationId', (int) $convA->id);
        $this->assertSame($convA->id, $pane->get('conversationId'));

        Livewire::actingAs($alice)
            ->test('chat.message-thread', ['conversationId' => $convA->id, 'isActive' => true])
            ->assertSee('Only in A');
    }

    public function test_message_thread_refreshes_viewport_when_reactivated_after_new_db_messages(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$convA] = $messaging->findOrCreateConversation($alice, $bob);

        $messaging->sendMessage($convA, $bob, 'First in A');

        $thread = Livewire::actingAs($alice)
            ->test('chat.message-thread', [
                'conversationId' => $convA->id,
                'isActive' => true,
            ]);

        $this->assertCount(1, $thread->get('messagesViewport'));

        $messaging->sendMessage($convA, $bob, 'Second in A');

        $thread
            ->set('isActive', false)
            ->set('isActive', true);

        $bodies = array_map(fn (array $row): string => (string) $row['body'], $thread->get('messagesViewport'));
        $this->assertContains('First in A', $bodies);
        $this->assertContains('Second in A', $bodies);
    }
}
