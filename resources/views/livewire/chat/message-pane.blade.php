<?php

use Phunky\Actions\Chat\LoadConversationMediaForViewer;
use Phunky\Livewire\Concerns\SerializesChatMessages;
use Phunky\Models\User;
use Phunky\Support\MessageAttachmentTypeRegistry;
use Illuminate\Validation\Rule;
use Phunky\LaravelMessagingAttachments\Attachment as MessageAttachment;
use Phunky\LaravelMessagingAttachments\AttachmentService;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Phunky\LaravelMessaging\Exceptions\CannotMessageException;
use Phunky\LaravelMessagingGroups\Group;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Services\MessagingService;

new class extends Component
{
    use SerializesChatMessages;
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

    protected function resetAttachmentPickerState(): void
    {
        $this->attachmentKind = MessageAttachmentTypeRegistry::defaultKind();
        $this->attachmentAccept = MessageAttachmentTypeRegistry::accept($this->attachmentKind);
        $this->voiceNoteError = '';
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

    public function prepareUpload(string $kind): void
    {
        if (! MessageAttachmentTypeRegistry::has($kind)) {
            return;
        }

        $this->pendingFiles = [];
        $this->attachmentKind = $kind;
        $this->attachmentAccept = MessageAttachmentTypeRegistry::accept($kind);
        $this->voiceNoteError = '';
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

    #[On('messaging-remote-message-sent')]
    public function onMessagingRemoteMessageSent(MessagingService $messaging, int $conversationId, int $messageId): void
    {
        if ($this->conversationId === null || (int) $conversationId !== (int) $this->conversationId) {
            return;
        }

        $this->markConversationDisplayedAsRead($messaging);
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
            ->whereIn('type', ['image', 'video'])
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
            $serialized = $this->serializeMessage($fresh);

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
                message: $this->serializeMessage($message),
            );

            $this->newMessage = '';
            $this->pendingFiles = [];
            $this->resetAttachmentPickerState();
            $this->pendingFilesInputKey++;

            if ($attachmentRows !== [] && in_array($this->attachmentKind, ['image', 'video'], true)) {
                $this->conversationHasMedia = true;
            }

            $this->markConversationDisplayedAsRead($messaging);

            $this->dispatch('conversation-updated');
            $this->js('queueMicrotask(() => requestAnimationFrame(() => window.stabilizeChatScrollToBottom?.()))');
        } catch (CannotMessageException $e) {
            $this->suppressPendingAttachmentPreview = false;
            $this->newMessage = $savedBody;
            $this->pendingFiles = $savedFiles;
            $this->addError('newMessage', $e->getMessage());
        } catch (\Throwable $e) {
            if ($message instanceof Message) {
                try {
                    $messaging->deleteMessage($message, $user);
                } catch (\Throwable) {
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
?>

<div class="flex h-full min-h-0 min-w-0 w-full flex-1 flex-col overflow-hidden">
    {{-- Empty state: keep @island out of @if (Livewire islands restriction). --}}
    <div
        class="@if ($conversationId !== null) hidden @endif flex min-h-0 flex-1 items-center justify-center p-8"
    >
        <flux:text class="text-center text-zinc-500">{{ __('Select a conversation to start messaging.') }}</flux:text>
    </div>

    <div
        class="@if ($conversationId === null) hidden @endif flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
    >
        <div
            class="relative z-20 shrink-0 border-b border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div class="flex min-w-0 items-center gap-3">
                @if ($conversationId !== null)
                    <flux:button
                        type="button"
                        variant="ghost"
                        size="sm"
                        icon="chevron-left"
                        wire:click="navigateBackToInbox"
                        class="-ml-2 shrink-0 lg:hidden text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                    >
                        <span class="sr-only">{{ __('Back') }}</span>
                    </flux:button>
                    <flux:avatar
                        :name="$headerTitle"
                        color="auto"
                        color:seed="{{ $conversationId }}"
                        size="sm"
                    />
                @endif
                <flux:heading size="md" level="2" class="min-w-0 truncate">{{ $headerTitle }}</flux:heading>
                @if ($isGroup)
                    <flux:badge size="sm" color="zinc">{{ __('Group') }}</flux:badge>
                @endif
                <flux:spacer />
                @if ($conversationId !== null && $conversationHasMedia)
                    <flux:button
                        type="button"
                        variant="ghost"
                        size="sm"
                        icon="photo"
                        wire:click="openConversationMediaViewer"
                        class="shrink-0 text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                    >
                        {{ __('Media') }}
                    </flux:button>
                @endif
            </div>
            @error('message_delete')
                <flux:text size="sm" class="mt-2 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror
        </div>

        <div class="flex min-h-0 w-full flex-1 flex-col overflow-hidden">
        <div
            id="chat-scroll-area"
            class="flex min-h-0 flex-1 basis-0 flex-col overflow-y-auto overscroll-contain py-3"
            x-data="{ ready: false }"
            x-init="$nextTick(() => { ready = true })"
        >
            @foreach ($warmConversationIds as $cid)
                <div
                    class="@if ((int) $conversationId !== (int) $cid) hidden @endif min-h-0"
                    wire:key="warm-wrap-{{ $cid }}"
                >
                    <livewire:chat.message-thread
                        :conversation-id="$cid"
                        :is-active="(int) $conversationId === (int) $cid"
                        wire:key="message-thread-{{ $cid }}"
                    />
                </div>
            @endforeach
        </div>

        <flux:modal wire:model.self="editModalOpen" wire:cancel="cancelEdit" class="md:w-lg">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Edit message') }}</flux:heading>
                <flux:textarea wire:model="editMessageBody" rows="6" />
                @error('editMessageBody')
                    <flux:text size="sm" class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="cancelEdit">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="button" variant="primary" wire:click="saveEdit">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal
            wire:model.self="deleteModalOpen"
            wire:cancel="cancelDeleteMessage"
            class="min-w-[22rem]"
        >
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete message?') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('This message will be removed from the conversation.') }}</flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="cancelDeleteMessage">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="button" variant="danger" wire:click="confirmDeleteMessage">
                        {{ __('Delete') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        @if ($mediaViewerOpen)
            <x-chat.conversation-media-viewer :items="$mediaViewerItems" :index="$mediaViewerIndex" />
        @endif

        <div class="shrink-0 w-full pb-4 px-4">
            <div class="mx-auto w-full max-w-4xl">
                <form wire:submit="sendMessage">
                    @php $pendingFileInputId = 'chat-pending-file-'.$this->getId(); @endphp
                    @if ($pendingFiles !== [] && ! $suppressPendingAttachmentPreview)
                        <div class="mb-2 flex flex-wrap gap-2">
                            @foreach ($pendingFiles as $index => $file)
                                @php
                                    $pendingMime = (string) $file->getMimeType();
                                    $isPendingAudio = str_starts_with($pendingMime, 'audio/');
                                    $canPreviewTmp = method_exists($file, 'isPreviewable') && $file->isPreviewable();
                                @endphp
                                <div
                                    @class([
                                        'relative overflow-hidden rounded-md border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800',
                                        'shrink-0' => ! $isPendingAudio,
                                        'h-16 w-16' => ! $isPendingAudio,
                                        'min-h-16 w-full max-w-xs px-2 py-1.5' => $isPendingAudio,
                                    ])
                                    wire:key="pending-file-{{ $index }}"
                                >
                                    @if (str_starts_with($pendingMime, 'image/') && $canPreviewTmp)
                                        <img
                                            src="{{ $file->temporaryUrl() }}"
                                            alt=""
                                            class="h-full w-full object-cover"
                                        />
                                    @elseif (str_starts_with($pendingMime, 'video/') && $canPreviewTmp)
                                        <video
                                            src="{{ $file->temporaryUrl() }}"
                                            muted
                                            playsinline
                                            preload="metadata"
                                            class="h-full w-full object-cover"
                                        ></video>
                                    @elseif (str_starts_with($pendingMime, 'image/') || str_starts_with($pendingMime, 'video/'))
                                        <div class="flex h-full w-full flex-col items-center justify-center gap-0.5 p-1 text-center">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.5"
                                                stroke="currentColor"
                                                class="size-6 shrink-0 text-zinc-500 dark:text-zinc-400"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"
                                                />
                                            </svg>
                                            <span class="line-clamp-2 w-full break-all text-[0.65rem] leading-tight text-zinc-600 dark:text-zinc-300">
                                                {{ $file->getClientOriginalName() }}
                                            </span>
                                        </div>
                                    @elseif ($isPendingAudio && $canPreviewTmp)
                                        <audio
                                            src="{{ $file->temporaryUrl() }}"
                                            controls
                                            preload="metadata"
                                            controlslist="nodownload noplaybackrate"
                                            class="chat-native-audio h-10 min-w-[220px] w-full"
                                        ></audio>
                                    @elseif ($isPendingAudio)
                                        <div class="flex h-full w-full flex-col items-center justify-center gap-0.5 p-1 text-center">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.5"
                                                stroke="currentColor"
                                                class="size-6 shrink-0 text-zinc-500 dark:text-zinc-400"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.829.112-1.632.338-2.396.234-.847.958-1.354 1.938-1.354H6.75Z"
                                                />
                                            </svg>
                                            <span class="line-clamp-2 w-full break-all text-[0.65rem] leading-tight text-zinc-600 dark:text-zinc-300">
                                                {{ $file->getClientOriginalName() }}
                                            </span>
                                        </div>
                                    @else
                                        <div class="flex h-full w-full flex-col items-center justify-center gap-0.5 p-1 text-center">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.5"
                                                stroke="currentColor"
                                                class="size-6 shrink-0 text-zinc-500 dark:text-zinc-400"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                                                />
                                            </svg>
                                            <span class="line-clamp-2 w-full break-all text-[0.65rem] leading-tight text-zinc-600 dark:text-zinc-300">
                                                {{ $file->getClientOriginalName() }}
                                            </span>
                                        </div>
                                    @endif
                                    <button
                                        type="button"
                                        wire:click="removePendingFile({{ $index }})"
                                        class="absolute end-0.5 top-0.5 flex size-5 items-center justify-center rounded-full bg-zinc-900/75 text-xs text-white hover:bg-zinc-900"
                                        title="{{ __('Remove') }}"
                                    >
                                        ×
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @error('pendingFiles.*')
                        <flux:text size="sm" class="mb-2 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                    @if ($voiceNoteError !== '')
                        <flux:text size="sm" class="mb-2 text-red-600 dark:text-red-400">{{ $voiceNoteError }}</flux:text>
                    @endif

                    <div class="w-full">
                        @php
                            $attachmentMaxFiles = (int) (MessageAttachmentTypeRegistry::definitions()[$attachmentKind]['max_files'] ?? 1);
                        @endphp
                        <input
                            id="{{ $pendingFileInputId }}"
                            type="file"
                            wire:key="pending-files-input-{{ $pendingFilesInputKey }}"
                            class="hidden"
                            wire:model="pendingFiles"
                            @if ($attachmentMaxFiles > 1) multiple @endif
                            accept="{{ $attachmentAccept }}"
                        />
                        @php
                            $hasComposerSendContent = trim($newMessage) !== '' || $pendingFiles !== [];
                        @endphp
                        <div
                            class="w-full"
                            x-data="chatVoiceNote({
                                errUnsupported: @js(__('Microphone recording is not supported in this browser.')),
                                errPermission: @js(__('Microphone permission was denied.')),
                                errUpload: @js(__('Could not upload the voice note. Please try again.')),
                            })"
                        >
                            <div x-show="!recording" class="w-full">
                                <flux:input
                                    wire:model.live="newMessage"
                                    placeholder="{{ __('Type a message…') }}"
                                    class="w-full"
                                >
                                    <x-slot name="iconLeading">
                                        <flux:dropdown position="top" align="start">
                                            <flux:button
                                                type="button"
                                                size="sm"
                                                variant="subtle"
                                                icon="paper-clip"
                                                class="-ms-1 shrink-0"
                                            />

                                            <flux:popover class="min-w-44 p-1">
                                                @foreach (collect(MessageAttachmentTypeRegistry::definitions())->except('voice_note') as $kind => $def)
                                                    <button
                                                        type="button"
                                                        class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                                        wire:key="attach-kind-{{ $kind }}"
                                                        @click.prevent="(async () => { await $wire.prepareUpload(@js($kind)); document.getElementById(@js($pendingFileInputId)).click() })()"
                                                    >
                                                        {{ __($def['label']) }}
                                                    </button>
                                                @endforeach
                                            </flux:popover>
                                        </flux:dropdown>
                                    </x-slot>
                                    <x-slot name="iconTrailing">
                                        <div class="-mr-1 flex items-center gap-0.5">
                                            @if (! $hasComposerSendContent)
                                                <flux:button
                                                    type="button"
                                                    size="sm"
                                                    variant="subtle"
                                                    icon="microphone"
                                                    class="shrink-0"
                                                    x-bind:disabled="processing"
                                                    title="{{ __('Record voice note') }}"
                                                    @click.prevent="toggle()"
                                                />
                                            @endif
                                            @if ($hasComposerSendContent)
                                                <flux:button
                                                    type="submit"
                                                    size="sm"
                                                    variant="subtle"
                                                    icon="paper-airplane"
                                                    class="shrink-0 data-loading:opacity-50 data-loading:pointer-events-none"
                                                />
                                            @endif
                                        </div>
                                    </x-slot>
                                </flux:input>
                            </div>

                            <div
                                x-cloak
                                x-show="recording"
                                class="flex w-full"
                                style="display: none;"
                            >
                                <audio
                                    x-ref="previewAudio"
                                    class="hidden"
                                    @loadedmetadata="onPreviewLoaded()"
                                    @ended="onPreviewEnded()"
                                ></audio>

                                <div
                                    class="flex h-10 w-full items-center gap-1.5 rounded-xl border border-zinc-200 bg-zinc-100 px-2 py-0 dark:border-zinc-700 dark:bg-zinc-800/90"
                                    role="region"
                                    aria-live="polite"
                                    aria-label="{{ __('Voice recording') }}"
                                >
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="subtle"
                                        icon="trash"
                                        class="shrink-0 text-zinc-600 dark:text-zinc-300"
                                        x-bind:disabled="processing"
                                        title="{{ __('Discard recording') }}"
                                        @click.prevent="discardRecording()"
                                    />
                                    <div class="relative z-10 flex shrink-0 items-center">
                                        <div x-show="paused && !previewListening" class="inline-flex">
                                            <flux:button
                                                type="button"
                                                size="sm"
                                                variant="subtle"
                                                icon="play"
                                                class="text-zinc-700 dark:text-zinc-200"
                                                x-bind:disabled="processing"
                                                title="{{ __('Play recording') }}"
                                                @click.prevent="togglePreviewPlayback()"
                                            />
                                        </div>
                                        <div x-show="previewListening" class="inline-flex">
                                            <flux:button
                                                type="button"
                                                size="sm"
                                                variant="subtle"
                                                icon="pause"
                                                class="text-zinc-700 dark:text-zinc-200"
                                                x-bind:disabled="processing"
                                                title="{{ __('Pause playback') }}"
                                                @click.prevent="togglePreviewPlayback()"
                                            />
                                        </div>
                                    </div>
                                    <div
                                        class="flex h-6 min-w-0 flex-1 items-end justify-center gap-px overflow-hidden rounded-md px-0.5"
                                        aria-hidden="true"
                                    >
                                        <template x-for="(height, index) in waveformBars" :key="index">
                                            <div
                                                class="w-0.5 shrink-0 rounded-full bg-zinc-500 transition-[height] duration-75 dark:bg-zinc-400"
                                                :style="`height: ${height}%; min-height: 2px`"
                                            ></div>
                                        </template>
                                    </div>
                                    <span
                                        class="w-10 shrink-0 tabular-nums text-xs text-zinc-800 dark:text-zinc-100"
                                        x-text="formatElapsed()"
                                    ></span>
                                    <div class="flex shrink-0 items-center gap-0.5">
                                        <flux:button
                                            type="button"
                                            size="sm"
                                            variant="subtle"
                                            icon="pause"
                                            class="!text-red-600 dark:!text-red-400"
                                            x-show="!paused"
                                            x-bind:disabled="processing"
                                            title="{{ __('Pause recording') }}"
                                            @click.prevent="togglePause()"
                                        />
                                        <flux:button
                                            type="button"
                                            size="sm"
                                            variant="subtle"
                                            icon="microphone"
                                            class="!text-red-600 dark:!text-red-400"
                                            x-show="paused"
                                            x-bind:disabled="processing"
                                            title="{{ __('Resume recording') }}"
                                            @click.prevent="togglePause()"
                                        />
                                    </div>
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="subtle"
                                        icon="paper-airplane"
                                        class="shrink-0 data-loading:opacity-50 data-loading:pointer-events-none"
                                        x-bind:disabled="processing"
                                        title="{{ __('Send voice note') }}"
                                        @click.prevent="finishRecording()"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    @error('newMessage')
                        <flux:text size="sm" class="mt-2 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                </form>
            </div>
        </div>
        </div>
    </div>
</div>
