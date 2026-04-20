<?php

use Phunky\Livewire\Concerns\SerializesChatMessages;
use Phunky\Models\User;
use Phunky\Support\Chat\MessageViewModel;
use Illuminate\Pagination\Cursor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;

new class extends Component
{
    use SerializesChatMessages;

    public int $conversationId;

    public bool $isActive = true;

    public bool $isGroup = false;

    /**
     * @var list<array{id: int, name: string}>
     */
    public array $typingUsers = [];

    /**
     * @var list<array{id: int, name: string}>
     */
    public array $recordingUsers = [];

    /**
     * @var list<array{id: int, body: string, sent_at: ?string, edited_at: ?string, sender_id: string, sender_name: string, is_me: bool, attachments: list<array{id: int, type: string, url: string, filename: string, mime_type: ?string, size: ?int}>}>
     */
    public array $messagesViewport = [];

    public bool $isIslandPartialRender = false;

    public bool $islandPartialIsPrepend = false;

    /** @var list<array{id: int, body: string, sent_at: ?string, edited_at: ?string, sender_id: string, sender_name: string, is_me: bool, attachments: list<array{id: int, type: string, url: string, filename: string, mime_type: ?string, size: ?int}>}> */
    public array $islandPartialRows = [];

    public ?string $messagesCursor = null;

    public bool $messagesHasMore = false;

    public function mount(int $conversationId, bool $isActive = true): void
    {
        $this->conversationId = $conversationId;
        $this->isActive = $isActive;
        $this->loadThreadFlags();
        $this->loadInitialMessagesPage();
    }

    public function updatedIsActive(bool $value): void
    {
        if ($value) {
            $this->refreshViewportIfDatabaseAhead();
        }
    }

    /**
     * When another tab had focus, new messages may exist in the DB while this
     * thread stayed mounted with a stale viewport — reload only if needed.
     */
    protected function refreshViewportIfDatabaseAhead(): void
    {
        $latestId = (int) Message::query()
            ->where('conversation_id', $this->conversationId)
            ->max('id');

        if ($latestId === 0) {
            return;
        }

        $localMax = $this->messagesViewport === []
            ? 0
            : max(array_map(fn (array $m): int => (int) $m['id'], $this->messagesViewport));

        if ($latestId > $localMax) {
            $this->loadInitialMessagesPage();
        }
    }

    protected function loadThreadFlags(): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $conversation = Conversation::query()->find($this->conversationId);
        if (! $conversation instanceof Conversation) {
            return;
        }

        $this->isGroup = \Phunky\LaravelMessagingGroups\Group::query()
            ->where('conversation_id', $conversation->id)
            ->exists();
    }

    public function loadOlder(): void
    {
        if (! $this->isActive || ! $this->messagesHasMore || $this->messagesCursor === null) {
            return;
        }

        $conversation = Conversation::query()->find($this->conversationId);
        if (! $conversation instanceof Conversation) {
            return;
        }

        $query = $conversation->messages()->with(['messageable', 'attachments'])->reorder()->latest('sent_at')->latest('id');

        $page = $query->cursorPaginate(50, ['*'], 'cursor', Cursor::fromEncoded($this->messagesCursor));

        $chunk = collect($page->items())->map(fn (Message $m) => $this->serializeMessage($m))->values()->all();

        if ($chunk === []) {
            $this->messagesHasMore = false;

            return;
        }

        $this->islandPartialRows = array_reverse($chunk);
        $this->isIslandPartialRender = true;
        $this->islandPartialIsPrepend = true;

        $this->messagesCursor = $page->nextCursor()?->encode();
        $this->messagesHasMore = $page->hasMorePages();
    }

    public function dehydrate(): void
    {
        if (! $this->isIslandPartialRender || $this->islandPartialRows === []) {
            return;
        }

        if ($this->islandPartialIsPrepend) {
            $this->messagesViewport = [...$this->islandPartialRows, ...$this->messagesViewport];
        } else {
            $this->messagesViewport = [...$this->messagesViewport, ...$this->islandPartialRows];
        }

        $this->isIslandPartialRender = false;
        $this->islandPartialRows = [];
    }

    protected function loadInitialMessagesPage(): void
    {
        $conversation = Conversation::query()->find($this->conversationId);
        if (! $conversation instanceof Conversation) {
            return;
        }

        $query = $conversation->messages()->with(['messageable', 'attachments'])->reorder()->latest('sent_at')->latest('id');

        $perPage = 50;

        $page = $query->cursorPaginate($perPage);
        $chunk = collect($page->items())->map(fn (Message $m) => $this->serializeMessage($m))->values()->all();
        $this->messagesViewport = array_reverse($chunk);
        $this->messagesCursor = $page->nextCursor()?->encode();
        $this->messagesHasMore = $page->hasMorePages();
        $this->isIslandPartialRender = false;
        $this->islandPartialRows = [];
    }

    /**
     * Replace a serialized message in the viewport by id. Returns true if a row was updated.
     */
    private function replaceViewportMessage(array $serialized): bool
    {
        $messageId = (int) ($serialized['id'] ?? 0);
        if ($messageId <= 0) {
            return false;
        }

        $replaced = false;
        $this->messagesViewport = array_map(
            function (array $row) use ($messageId, $serialized, &$replaced): array {
                if ((int) $row['id'] === $messageId) {
                    $replaced = true;

                    return $serialized;
                }

                return $row;
            },
            $this->messagesViewport,
        );

        return $replaced;
    }

    /**
     * Upsert a serialized message into the viewport by id. Existing rows are
     * replaced; new rows are appended.
     */
    private function upsertViewportMessage(array $serialized, bool $scrollOnInsert = false): bool
    {
        $inserted = ! $this->replaceViewportMessage($serialized);

        if ($inserted) {
            $this->messagesViewport = [...$this->messagesViewport, $serialized];
            if ($scrollOnInsert) {
                $this->stabilizeChatScroll();
            }
        }

        $this->isIslandPartialRender = false;
        $this->islandPartialRows = [];

        return $inserted;
    }

    #[On('chat-message-appended')]
    public function onChatMessageAppended(int $conversationId, array $message): void
    {
        if ($conversationId !== $this->conversationId) {
            return;
        }

        $this->upsertViewportMessage($message);
    }

    #[On('chat-message-replaced')]
    public function onChatMessageReplaced(int $conversationId, array $message): void
    {
        if ($conversationId !== $this->conversationId) {
            return;
        }

        $this->replaceViewportMessage($message);
    }

    #[On('chat-message-removed')]
    public function onChatMessageRemoved(int $conversationId, int $messageId): void
    {
        if ($conversationId !== $this->conversationId) {
            return;
        }

        $this->messagesViewport = array_values(array_filter(
            $this->messagesViewport,
            fn (array $row) => (int) $row['id'] !== $messageId,
        ));
    }

    /**
     * @param  list<array{id: int|string, name: string}>  $users
     * @return list<array{id: int, name: string}>
     */
    private function sanitizeWhisperUsers(array $users): array
    {
        $selfKey = auth()->id() !== null ? (string) auth()->id() : null;

        return array_values(array_filter(array_map(
            static function (array $row): ?array {
                $id = $row['id'] ?? null;
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($id === null || $name === '') {
                    return null;
                }

                return ['id' => (int) $id, 'name' => $name];
            },
            $users,
        ), static fn (?array $row): bool => $row !== null && ($selfKey === null || (string) $row['id'] !== $selfKey)));
    }

    private function revealWhisperCardInViewport(): void
    {
        $this->revealChatBottomIfNearBottom();
    }

    /**
     * @param  list<array{id: int|string, name: string}>  $typingUsers
     */
    #[On('messaging-typing-updated')]
    public function onMessagingTypingUpdated(int $conversationId, array $typingUsers): void
    {
        if (! $this->isActive || $conversationId !== $this->conversationId) {
            return;
        }

        $previous = $this->typingUsers;
        $this->typingUsers = $this->sanitizeWhisperUsers($typingUsers);

        if ($previous === [] && $this->typingUsers !== [] && $this->recordingUsers === []) {
            $this->revealWhisperCardInViewport();
        }
    }

    /**
     * @param  list<array{id: int|string, name: string}>  $recordingUsers
     */
    #[On('messaging-recording-updated')]
    public function onMessagingRecordingUpdated(int $conversationId, array $recordingUsers): void
    {
        if (! $this->isActive || $conversationId !== $this->conversationId) {
            return;
        }

        $previous = $this->recordingUsers;
        $this->recordingUsers = $this->sanitizeWhisperUsers($recordingUsers);

        if ($previous === [] && $this->recordingUsers !== []) {
            $this->revealWhisperCardInViewport();
        }
    }

    #[On('messaging-remote-message-sent')]
    public function onRemoteMessageSent(int $conversationId, int $messageId): void
    {
        if (! $this->isActive || $conversationId !== $this->conversationId) {
            return;
        }

        $this->typingUsers = [];
        $this->recordingUsers = [];

        $message = Message::query()->with(['messageable', 'attachments'])->find($messageId);
        if (! $message instanceof Message || (int) $message->conversation_id !== $this->conversationId) {
            return;
        }

        $this->upsertViewportMessage($this->serializeMessage($message), scrollOnInsert: true);
    }

    #[On('messaging-remote-message-edited')]
    public function onRemoteMessageEdited(int $conversationId, int $messageId): void
    {
        if (! $this->isActive || $conversationId !== $this->conversationId) {
            return;
        }

        $message = Message::query()->with(['messageable', 'attachments'])->find($messageId);
        if (! $message instanceof Message || (int) $message->conversation_id !== $this->conversationId) {
            return;
        }

        $serialized = $this->serializeMessage($message);

        $this->replaceViewportMessage($serialized);
    }

    #[On('messaging-remote-message-deleted')]
    public function onRemoteMessageDeleted(int $conversationId, int $messageId): void
    {
        if (! $this->isActive || $conversationId !== $this->conversationId) {
            return;
        }

        $this->onChatMessageRemoved($conversationId, $messageId);
    }

    /**
     * Voice notes + other attachments land on the sender's message *after* the
     * MessageSent broadcast goes out (attachMany runs post-send). Re-serialize
     * the affected row so the attachment strip appears on the recipient once
     * the attachment event arrives.
     */
    #[On('messaging-remote-attachment-updated')]
    public function onRemoteAttachmentUpdated(int $conversationId, int $messageId): void
    {
        if (! $this->isActive || $conversationId !== $this->conversationId) {
            return;
        }

        $message = Message::query()->with(['messageable', 'attachments'])->find($messageId);
        if (! $message instanceof Message || (int) $message->conversation_id !== $this->conversationId) {
            return;
        }

        $this->upsertViewportMessage($this->serializeMessage($message), scrollOnInsert: true);
    }

    /**
     * Viewport rows as {@see MessageViewModel} instances with day-bucket flags applied.
     *
     * @return list<MessageViewModel>
     */
    #[Computed]
    public function renderedMessages(): array
    {
        $source = $this->isIslandPartialRender ? $this->islandPartialRows : $this->messagesViewport;

        return MessageViewModel::listFromArray($source);
    }
};
?>

<div class="mx-auto w-full max-w-4xl px-4" wire:key="message-thread-inner-{{ $conversationId }}">
    @island(name: 'messages', always: true)
        <div class="w-full">
            @if ($isActive && $messagesHasMore && $messagesCursor !== null)
                <div
                    class="shrink-0"
                    x-data="{ ready: true }"
                    wire:intersect.margin.200px="loadOlder"
                    wire:island.prepend="messages"
                >
                    <div class="h-2 w-full"></div>
                    <flux:text size="sm" class="hidden py-1 text-center text-zinc-400 in-data-loading:block">
                        {{ __('Loading older messages…') }}
                    </flux:text>
                </div>
            @endif

            <div class="flex flex-col gap-3">
                @foreach ($this->renderedMessages as $vm)
                    @if ($vm->isFirstOfDay)
                        <livewire:chat.date-separator
                            :sent-at="$vm->sentAt"
                            wire:key="date-separator-{{ $conversationId }}-{{ $vm->dayBucket() }}"
                        />
                    @endif

                    <livewire:chat.message-card
                        :message="$vm->toArray()"
                        :conversation-id="$conversationId"
                        :is-group="$isGroup"
                        :key="'msg-'.$vm->id"
                    />
                @endforeach

            </div>
        </div>
    @endisland

    @if ($isActive && $recordingUsers !== [])
        <div class="my-3" wire:key="thread-recording-card-wrap-{{ $conversationId }}">
            <x-chat.whisper-message-card :users="$recordingUsers" variant="recording" />
        </div>
    @elseif ($isActive && $typingUsers !== [])
        <div class="my-3" wire:key="thread-typing-card-wrap-{{ $conversationId }}">
            <x-chat.whisper-message-card :users="$typingUsers" variant="typing" />
        </div>
    @endif
</div>
