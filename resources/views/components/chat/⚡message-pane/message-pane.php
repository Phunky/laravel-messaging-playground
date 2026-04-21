<?php

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Phunky\Actions\Chat\LoadConversationMediaForViewer;
use Phunky\LaravelMessaging\Exceptions\CannotMessageException;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessagingAttachments\Attachment as MessageAttachment;
use Phunky\LaravelMessagingAttachments\AttachmentService;
use Phunky\LaravelMessagingGroups\Group;
use Phunky\Livewire\Concerns\SerializesChatMessages;
use Phunky\Livewire\Concerns\TracksOpenConversationWhispers;
use Phunky\Models\User;
use Phunky\Support\Chat\PendingAttachmentView;
use Phunky\Support\MessageAttachmentTypeRegistry;

new class extends Component
{
    use SerializesChatMessages;
    use TracksOpenConversationWhispers;
    use WithFileUploads;

    public ?int $conversationId = null;

    /**
     * Avoid re-running expensive hydrate when the same conversation is applied again.
     */
    protected ?int $lastHydratedConversationId = null;

    /**
     * Conversations with a mounted message-thread child (MRU order, max 3).
     *
     * @var list<int>
     */
    public array $warmConversationIds = [];

    /**
     * Staged files (uploaded to Livewire temp storage until send). Only one
     * attachment kind is active at a time; see {@see $attachmentKind}.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $pendingFiles = [];

    /**
     * Which configured attachment kind is being staged ({@see config('messaging.media_attachment_types')} keys).
     */
    public string $attachmentKind = 'image';

    /**
     * HTML `accept` attribute for the hidden file input (synced with {@see $attachmentKind}).
     */
    public string $attachmentAccept = 'image/*';

    public ?int $editingMessageId = null;

    public string $editMessageBody = '';

    public bool $editModalOpen = false;

    public bool $deleteModalOpen = false;

    public ?int $pendingDeleteMessageId = null;

    public string $newMessage = '';

    /**
     * Inline error for tap-to-record voice note (set from Alpine / server).
     */
    public string $voiceNoteError = '';

    /**
     * Inline error for video note recording (set from Alpine / server).
     */
    public string $videoNoteError = '';

    /**
     * When true, staged attachment chips stay hidden (voice note sends immediately after upload).
     */
    public bool $suppressPendingAttachmentPreview = false;

    public string $headerTitle = '';

    public bool $isGroup = false;

    /**
     * Bumped to remount the hidden file input after send or conversation switch.
     */
    public int $pendingFilesInputKey = 0;

    public bool $mediaViewerOpen = false;

    /**
     * @var list<array{id: int, type: string, url: string, mime_type: ?string, filename: string, message_id: int}>
     */
    public array $mediaViewerItems = [];

    public int $mediaViewerIndex = 0;

    /**
     * Whether the conversation has any image or video rows (for header Media affordance).
     */
    public bool $conversationHasMedia = false;

    public function mount(): void
    {
        $this->resetAttachmentPickerState();

        if ($this->conversationId !== null) {
            $this->hydrateOpenConversation($this->conversationId);
        }
    }

    /**
     * Stable id for the hidden file input, so the attachment-kind dropdown can
     * trigger it from Alpine via `document.getElementById`.
     */
    #[Computed]
    public function pendingFileInputId(): string
    {
        return 'chat-pending-file-'.$this->getId();
    }

    /**
     * How many files the current attachment kind allows (drives `multiple`
     * attribute + validation rules).
     */
    #[Computed]
    public function attachmentMaxFiles(): int
    {
        return (int) (MessageAttachmentTypeRegistry::definitions()[$this->attachmentKind]['max_files'] ?? 1);
    }

    /**
     * True when the composer should show the Send action (as opposed to the
     * hold-to-record voice note affordance).
     */
    #[Computed]
    public function hasComposerSendContent(): bool
    {
        return trim($this->newMessage) !== '' || $this->pendingFiles !== [];
    }

    /**
     * Pending-file preview rows wrapped as a DTO so the composer template can
     * render mime/preview branches without inline `method_exists` calls.
     *
     * @return list<PendingAttachmentView>
     */
    #[Computed]
    public function pendingAttachments(): array
    {
        $out = [];
        foreach ($this->pendingFiles as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $out[] = new PendingAttachmentView($file);
            }
        }

        return $out;
    }

    protected function resetAttachmentPickerState(): void
    {
        $this->attachmentKind = MessageAttachmentTypeRegistry::defaultKind();
        $this->attachmentAccept = MessageAttachmentTypeRegistry::accept($this->attachmentKind);
        $this->voiceNoteError = '';
        $this->videoNoteError = '';
        $this->suppressPendingAttachmentPreview = false;
    }

    /**
     * Prepare a voice-note upload that will be sent immediately without showing the pending strip.
     */
    public function prepareVoiceNoteForImmediateSend(): void
    {
        if (! MessageAttachmentTypeRegistry::has('voice_note')) {
            return;
        }

        $this->suppressPendingAttachmentPreview = true;
        $this->prepareUpload('voice_note');
    }

    public function clearVoiceNoteError(): void
    {
        $this->voiceNoteError = '';
    }

    public function setVoiceNoteError(string $message): void
    {
        $this->voiceNoteError = $message;
        $this->suppressPendingAttachmentPreview = false;
    }

    /**
     * Prepare a video-note upload (hides the composer chip) right before {@see sendMessage()}
     * after the user confirms on the client preview screen.
     */
    public function prepareVideoNoteForImmediateSend(): void
    {
        if (! MessageAttachmentTypeRegistry::has('video_note')) {
            return;
        }

        $this->suppressPendingAttachmentPreview = true;
        $this->prepareUpload('video_note');
    }

    public function clearVideoNoteError(): void
    {
        $this->videoNoteError = '';
    }

    public function setVideoNoteError(string $message): void
    {
        $this->videoNoteError = $message;
        $this->suppressPendingAttachmentPreview = false;
    }

    public function prepareUpload(string $kind): void
    {
        if (! MessageAttachmentTypeRegistry::has($kind)) {
            return;
        }

        $this->pendingFiles = [];
        $this->attachmentKind = $kind;
        $this->attachmentAccept = MessageAttachmentTypeRegistry::accept($kind);
        $this->voiceNoteError = '';
        $this->videoNoteError = '';
        $this->pendingFilesInputKey++;
        $this->resetValidation();
    }

    public function updatedConversationId(?int $value): void
    {
        if ($value === null) {
            $this->lastHydratedConversationId = null;
            if (config('messaging.broadcasting.enabled')) {
                $this->js('window.__chatMessagingEcho?.leave()');
            }

            return;
        }

        $this->hydrateOpenConversation($value);
    }

    /**
     * Sync thread UI when {@see $conversationId} is set from the page parent or via tests.
     */
    protected function hydrateOpenConversation(int $conversationId): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $conversation = Conversation::query()->find($conversationId);
        if (! $conversation instanceof Conversation) {
            return;
        }

        if (! $user->conversations()->whereKey($conversationId)->exists()) {
            return;
        }

        if ($this->lastHydratedConversationId === $conversationId) {
            return;
        }

        $this->ensureConversationWarmed($conversationId);

        $this->newMessage = '';
        $this->pendingFiles = [];
        $this->voiceNoteError = '';
        $this->videoNoteError = '';
        $this->resetAttachmentPickerState();
        $this->pendingFilesInputKey++;
        $this->editingMessageId = null;
        $this->editMessageBody = '';
        $this->editModalOpen = false;
        $this->deleteModalOpen = false;
        $this->pendingDeleteMessageId = null;
        $this->resetValidation();

        $this->mediaViewerOpen = false;
        $this->mediaViewerItems = [];
        $this->mediaViewerIndex = 0;
        $this->conversationHasMedia = false;
        $this->clearOpenConversationWhispers();

        $this->loadHeader($conversation, $user);
        $this->refreshConversationHasMediaFlag();

        $this->markConversationDisplayedAsRead(app(MessagingService::class));
        $this->lastHydratedConversationId = $conversationId;

        $this->dispatch('conversation-updated');

        $this->js(<<<'JS'
queueMicrotask(() => {
    const e = document.getElementById('chat-scroll-area')
    if (! e) return
    const api = e._x_dataStack?.[0]
    if (api) api.ready = false
    requestAnimationFrame(() => {
        window.stabilizeChatScrollToBottom?.()
        if (api) api.ready = true
    })
})
JS);

        if (config('messaging.broadcasting.enabled')) {
            $this->js('queueMicrotask(() => window.__chatMessagingEcho?.subscribe('.(int) $conversationId.'))');
        }
    }

    /**
     * Messages visible in the pane are treated as read for the current user.
     */
    protected function markConversationDisplayedAsRead(MessagingService $messaging): void
    {
        $user = auth()->user();
        if (! $user instanceof User || $this->conversationId === null) {
            return;
        }

        $conversation = Conversation::query()->find($this->conversationId);
        if (! $conversation instanceof Conversation) {
            return;
        }

        if (! $user->conversations()->whereKey($this->conversationId)->exists()) {
            return;
        }

        $messaging->markAllRead($conversation, $user);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeMessageForDispatch(Message $message): array
    {
        $conversation = Conversation::query()->find($message->conversation_id);
        $rows = $this->hydrateReadReceipts([$this->serializeMessage($message)], $conversation);

        return $rows[0] ?? $this->serializeMessage($message);
    }

    #[On('messaging-remote-message-sent')]
    public function onMessagingRemoteMessageSent(MessagingService $messaging, int $conversationId, int $messageId): void
    {
        if ($this->conversationId === null || (int) $conversationId !== (int) $this->conversationId) {
            return;
        }

        $this->markConversationDisplayedAsRead($messaging);
        $this->typingUsers = [];
        $this->recordingUsers = [];
    }

    /**
     * @param  list<array{id: int|string, name: string}>  $typingUsers
     */
    #[On('messaging-typing-updated')]
    public function onMessagingTypingUpdated(int $conversationId, array $typingUsers): void
    {
        $this->applyOpenConversationWhisperUpdate('typing', $conversationId, $typingUsers);
    }

    /**
     * @param  list<array{id: int|string, name: string}>  $recordingUsers
     */
    #[On('messaging-recording-updated')]
    public function onMessagingRecordingUpdated(int $conversationId, array $recordingUsers): void
    {
        $this->applyOpenConversationWhisperUpdate('recording', $conversationId, $recordingUsers);
    }

    public function navigateBackToInbox(): void
    {
        $this->dispatch('chat-mobile-back');
    }

    protected function ensureConversationWarmed(int $conversationId): void
    {
        $this->warmConversationIds = array_values(array_filter(
            $this->warmConversationIds,
            fn (int $id): bool => $id !== $conversationId
        ));
        $this->warmConversationIds[] = $conversationId;

        $max = 3;
        while (count($this->warmConversationIds) > $max) {
            array_shift($this->warmConversationIds);
        }
    }

    protected function loadHeader(Conversation $conversation, User $user): void
    {
        $conversation->load(['participants.messageable']);
        $group = Group::query()->where('conversation_id', $conversation->id)->first();

        if ($group instanceof Group) {
            $this->headerTitle = $group->name;
            $this->isGroup = true;

            return;
        }

        $other = $conversation->participants
            ->map(fn ($p) => $p->messageable)
            ->filter()
            ->first(fn ($m) => $m && (string) $m->getKey() !== (string) $user->getKey());

        $this->headerTitle = $other instanceof User ? $other->name : __('Conversation');
        $this->isGroup = false;
    }

    #[On('message-pane-start-edit')]
    public function onMessagePaneStartEdit(int $messageId): void
    {
        $this->startEdit($messageId);
    }

    #[On('message-pane-request-delete')]
    public function onMessagePaneRequestDelete(int $messageId): void
    {
        $this->requestDeleteMessage($messageId);
    }

    #[On('message-pane-open-media-viewer')]
    public function onMessagePaneOpenMediaViewer(int $attachmentId, ?int $messageId = null): void
    {
        $this->openMediaViewer($attachmentId, $messageId);
    }

    public function openMediaViewer(int $attachmentId, ?int $messageId = null): void
    {
        $user = auth()->user();
        if (! $user instanceof User || $this->conversationId === null) {
            return;
        }

        $items = (app(LoadConversationMediaForViewer::class))($user, $this->conversationId, $messageId);
        if ($items === []) {
            return;
        }

        $idx = null;
        foreach ($items as $i => $row) {
            if ((int) $row['id'] === $attachmentId) {
                $idx = $i;

                break;
            }
        }

        if ($idx === null) {
            return;
        }

        $this->mediaViewerItems = $items;
        $this->mediaViewerIndex = $idx;
        $this->mediaViewerOpen = true;
    }

    public function closeMediaViewer(): void
    {
        $this->mediaViewerOpen = false;
    }

    public function mediaViewerGo(int $delta): void
    {
        if (! $this->mediaViewerOpen || $this->mediaViewerItems === []) {
            return;
        }

        $count = count($this->mediaViewerItems);
        if ($count === 0) {
            return;
        }

        $next = ($this->mediaViewerIndex + $delta) % $count;
        if ($next < 0) {
            $next += $count;
        }

        $this->mediaViewerIndex = $next;
    }

    public function mediaViewerSetIndex(int $index): void
    {
        if (! $this->mediaViewerOpen || $this->mediaViewerItems === []) {
            return;
        }

        if ($index < 0 || $index >= count($this->mediaViewerItems)) {
            return;
        }

        $this->mediaViewerIndex = $index;
    }

    /**
     * Open the viewer on the first image/video in chronological order (header shortcut).
     */
    public function openConversationMediaViewer(): void
    {
        $user = auth()->user();
        if (! $user instanceof User || $this->conversationId === null) {
            return;
        }

        $items = (app(LoadConversationMediaForViewer::class))($user, $this->conversationId);
        if ($items === []) {
            return;
        }

        $this->mediaViewerItems = $items;
        $this->mediaViewerIndex = 0;
        $this->mediaViewerOpen = true;
    }

    protected function refreshConversationHasMediaFlag(): void
    {
        if ($this->conversationId === null) {
            $this->conversationHasMedia = false;

            return;
        }

        $this->conversationHasMedia = MessageAttachment::query()
            ->where('conversation_id', $this->conversationId)
            ->whereIn('type', ['image', 'video', 'video_note'])
            ->exists();
    }

    public function startEdit(int $messageId): void
    {
        $messageId = (int) $messageId;
        $user = auth()->user();
        if (! $user instanceof User || $this->conversationId === null) {
            return;
        }

        $message = Message::query()->with('messageable')->find($messageId);
        if (! $message instanceof Message || (int) $message->conversation_id !== (int) $this->conversationId) {
            return;
        }

        $sender = $message->messageable;
        if (! $sender instanceof User || (string) $sender->getKey() !== (string) $user->getKey()) {
            return;
        }

        $this->editingMessageId = $messageId;
        $this->editMessageBody = (string) $message->body;
        $this->editModalOpen = true;
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingMessageId = null;
        $this->editMessageBody = '';
        $this->editModalOpen = false;
        $this->resetValidation();
    }

    public function updatedEditModalOpen(bool $value): void
    {
        if (! $value) {
            $this->editingMessageId = null;
            $this->editMessageBody = '';
            $this->resetValidation();
        }
    }

    public function updatedDeleteModalOpen(bool $value): void
    {
        if (! $value) {
            $this->pendingDeleteMessageId = null;
        }
    }

    public function requestDeleteMessage(int $messageId): void
    {
        $messageId = (int) $messageId;
        $user = auth()->user();
        if (! $user instanceof User || $this->conversationId === null) {
            return;
        }

        $message = Message::query()->with('messageable')->find($messageId);
        if (! $message instanceof Message || (int) $message->conversation_id !== (int) $this->conversationId) {
            return;
        }

        $sender = $message->messageable;
        if (! $sender instanceof User || (string) $sender->getKey() !== (string) $user->getKey()) {
            return;
        }

        $this->pendingDeleteMessageId = $messageId;
        $this->deleteModalOpen = true;
    }

    public function cancelDeleteMessage(): void
    {
        $this->deleteModalOpen = false;
        $this->pendingDeleteMessageId = null;
    }

    public function confirmDeleteMessage(MessagingService $messaging): void
    {
        if ($this->pendingDeleteMessageId === null) {
            return;
        }

        $id = $this->pendingDeleteMessageId;
        $this->deleteModalOpen = false;
        $this->pendingDeleteMessageId = null;

        $this->deleteMessage($messaging, $id);
    }

    public function saveEdit(MessagingService $messaging): void
    {
        $this->validate(['editMessageBody' => 'required|string|max:65535']);

        $user = auth()->user();
        if (! $user instanceof User || ! $this->conversationId || $this->editingMessageId === null) {
            $this->cancelEdit();

            return;
        }

        /** @var Message|null $message */
        $message = Message::query()->find($this->editingMessageId);
        if (! $message instanceof Message || (int) $message->conversation_id !== $this->conversationId) {
            $this->cancelEdit();

            return;
        }

        try {
            $fresh = $messaging->editMessage($message, $user, $this->editMessageBody);
            $fresh->load(['messageable', 'attachments']);
            $serialized = $this->serializeMessageForDispatch($fresh);

            $this->dispatch(
                'chat-message-replaced',
                conversationId: (int) $fresh->conversation_id,
                message: $serialized,
            );

            $this->cancelEdit();
            $this->dispatch('conversation-updated');
        } catch (CannotMessageException $e) {
            $this->addError('editMessageBody', $e->getMessage());
        }
    }

    public function deleteMessage(MessagingService $messaging, int $messageId): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $this->conversationId) {
            return;
        }

        $messageId = (int) $messageId;

        /** @var Message|null $message */
        $message = Message::query()->with('messageable')->find($messageId);
        if (! $message instanceof Message || (int) $message->conversation_id !== (int) $this->conversationId) {
            return;
        }

        $sender = $message->messageable;
        if (! $sender instanceof User || (string) $sender->getKey() !== (string) $user->getKey()) {
            return;
        }

        try {
            $messaging->deleteMessage($message, $user);

            $this->dispatch(
                'chat-message-removed',
                conversationId: (int) $this->conversationId,
                messageId: $messageId,
            );

            if ($this->editingMessageId === $messageId) {
                $this->cancelEdit();
            }

            $this->dispatch('conversation-updated');
            $this->refreshConversationHasMediaFlag();
        } catch (CannotMessageException $e) {
            $this->addError('message_delete', $e->getMessage());
        }
    }

    public function removePendingFile(int $index): void
    {
        if (! isset($this->pendingFiles[$index])) {
            return;
        }

        $next = $this->pendingFiles;
        unset($next[$index]);
        $this->pendingFiles = array_values($next);
    }

    public function sendMessage(MessagingService $messaging, AttachmentService $attachmentService): void
    {
        $rules = [
            'newMessage' => ['nullable', 'string', 'max:65535'],
            'attachmentKind' => ['required', 'string', Rule::in(array_keys(MessageAttachmentTypeRegistry::definitions()))],
        ];

        if ($this->pendingFiles !== []) {
            if (! MessageAttachmentTypeRegistry::has($this->attachmentKind)) {
                $this->addError('attachmentKind', __('Invalid attachment type.'));

                return;
            }
            $rules = array_merge($rules, MessageAttachmentTypeRegistry::validationRules($this->attachmentKind));
        } else {
            $rules['pendingFiles'] = ['nullable', 'array'];
        }

        $this->validate($rules);

        $body = trim($this->newMessage);
        $files = $this->pendingFiles;

        if ($body === '' && $files === []) {
            $this->addError('newMessage', __('Please enter a message or add an attachment.'));

            return;
        }

        $user = auth()->user();
        if (! $user instanceof User || ! $this->conversationId) {
            return;
        }

        $conversation = Conversation::query()->find($this->conversationId);
        if (! $conversation instanceof Conversation) {
            return;
        }

        $savedBody = $this->newMessage;
        $savedFiles = $this->pendingFiles;

        $message = null;

        try {
            $message = $messaging->sendMessage($conversation, $user, $body);

            $diskName = config('messaging.media_disk');
            $attachmentRows = [];

            foreach ($files as $file) {
                if (! $file instanceof TemporaryUploadedFile) {
                    continue;
                }

                $directory = sprintf(
                    'messaging/%s/%s',
                    $conversation->getKey(),
                    $message->getKey(),
                );

                $storedPath = $file->store($directory, $diskName);

                $attachmentRows[] = [
                    'type' => $this->attachmentKind,
                    'path' => $storedPath,
                    'filename' => $file->getClientOriginalName(),
                    'disk' => $diskName,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }

            if ($attachmentRows !== []) {
                $attachmentService->attachMany($message, $user, $attachmentRows);
            }

            $message->load(['messageable', 'attachments']);

            $this->dispatch(
                'chat-message-appended',
                conversationId: (int) $conversation->getKey(),
                message: $this->serializeMessageForDispatch($message),
            );

            $this->newMessage = '';
            $this->pendingFiles = [];
            $this->resetAttachmentPickerState();
            $this->pendingFilesInputKey++;

            if ($attachmentRows !== [] && in_array($this->attachmentKind, ['image', 'video', 'video_note'], true)) {
                $this->conversationHasMedia = true;
            }

            $this->markConversationDisplayedAsRead($messaging);

            $this->dispatch('conversation-updated');
            $this->stabilizeChatScroll();
        } catch (CannotMessageException $e) {
            $this->suppressPendingAttachmentPreview = false;
            $this->newMessage = $savedBody;
            $this->pendingFiles = $savedFiles;
            $this->addError('newMessage', $e->getMessage());
        } catch (Throwable $e) {
            if ($message instanceof Message) {
                try {
                    $messaging->deleteMessage($message, $user);
                } catch (Throwable) {
                }
            }

            report($e);
            $this->suppressPendingAttachmentPreview = false;
            $this->newMessage = $savedBody;
            $this->pendingFiles = $savedFiles;
            $this->addError('newMessage', __('Could not send your message. Please try again.'));
        }
    }
};
