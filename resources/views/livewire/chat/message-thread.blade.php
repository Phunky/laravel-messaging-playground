<?php

use Phunky\Livewire\Concerns\SerializesChatMessages;
use Phunky\Models\User;
use Illuminate\Pagination\Cursor;
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
        $this->loadMessagesPage(reset: true);
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
            $this->loadMessagesPage(reset: true);
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

    protected function loadMessagesPage(bool $reset): void
    {
        $conversation = Conversation::query()->find($this->conversationId);
        if (! $conversation instanceof Conversation) {
            return;
        }

        $query = $conversation->messages()->with(['messageable', 'attachments'])->reorder()->latest('sent_at')->latest('id');

        $perPage = 50;

        if ($reset) {
            $page = $query->cursorPaginate($perPage);
            $chunk = collect($page->items())->map(fn (Message $m) => $this->serializeMessage($m))->values()->all();
            $this->messagesViewport = array_reverse($chunk);
            $this->messagesCursor = $page->nextCursor()?->encode();
            $this->messagesHasMore = $page->hasMorePages();
            $this->isIslandPartialRender = false;
            $this->islandPartialRows = [];
        }
    }

    #[On('chat-message-appended')]
    public function onChatMessageAppended(int $conversationId, array $message): void
    {
        if ($conversationId !== $this->conversationId) {
            return;
        }

        $this->messagesViewport = [...$this->messagesViewport, $message];
        $this->isIslandPartialRender = false;
        $this->islandPartialRows = [];
    }

    #[On('chat-message-replaced')]
    public function onChatMessageReplaced(int $conversationId, array $message): void
    {
        if ($conversationId !== $this->conversationId) {
            return;
        }

        $this->messagesViewport = array_map(
            fn (array $row) => (int) $row['id'] === (int) $message['id'] ? $message : $row,
            $this->messagesViewport,
        );
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

    #[On('messaging-remote-message-sent')]
    public function onRemoteMessageSent(int $conversationId, int $messageId): void
    {
        if (! $this->isActive || $conversationId !== $this->conversationId) {
            return;
        }

        foreach ($this->messagesViewport as $row) {
            if ((int) $row['id'] === $messageId) {
                return;
            }
        }

        $message = Message::query()->with(['messageable', 'attachments'])->find($messageId);
        if (! $message instanceof Message || (int) $message->conversation_id !== $this->conversationId) {
            return;
        }

        $this->messagesViewport = [...$this->messagesViewport, $this->serializeMessage($message)];
        $this->isIslandPartialRender = false;
        $this->islandPartialRows = [];

        $this->js('queueMicrotask(() => requestAnimationFrame(() => window.stabilizeChatScrollToBottom?.()))');
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

        $this->messagesViewport = array_map(
            fn (array $row) => (int) $row['id'] === $messageId ? $serialized : $row,
            $this->messagesViewport,
        );
    }

    #[On('messaging-remote-message-deleted')]
    public function onRemoteMessageDeleted(int $conversationId, int $messageId): void
    {
        if (! $this->isActive || $conversationId !== $this->conversationId) {
            return;
        }

        $this->onChatMessageRemoved($conversationId, $messageId);
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

            @php $prevDate = null; @endphp

            <div class="flex flex-col gap-3">
                @foreach (($isIslandPartialRender ? $islandPartialRows : $messagesViewport) as $msg)
                    @php
                        $currentDate = ! empty($msg['sent_at']) ? \Illuminate\Support\Carbon::parse($msg['sent_at'])->timezone(config('app.timezone'))->toDateString() : null;
                    @endphp

                    @if ($currentDate !== null && $currentDate !== $prevDate)
                        <livewire:chat.date-separator
                            :sent-at="$msg['sent_at']"
                            wire:key="date-separator-{{ $conversationId }}-{{ $currentDate }}"
                        />
                    @endif

                    @php $prevDate = $currentDate; @endphp

                    <x-chat.message-card :msg="$msg" :conversation-id="$conversationId" :is-group="$isGroup" />
                @endforeach
            </div>
        </div>
    @endisland
</div>
