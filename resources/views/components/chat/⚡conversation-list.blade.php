<?php

use Phunky\Actions\Chat\ListConversationInboxRows;
use Phunky\Livewire\Concerns\TracksInboxWhispers;
use Phunky\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    use TracksInboxWhispers;

    public ?int $selectedConversationId = null;

    /** @var list<array{conversation_id: int, title: string, subtitle: string, is_group: bool, updated_at: ?string, unread_count: int, other_participant_ids: list<int>}> */
    public array $rows = [];

    public ?string $nextCursor = null;

    public bool $hasMore = true;

    /**
     * Users present on each conversation's presence channel (== online with
     * that thread open). Keyed by conversation id.
     *
     * @var array<int, list<int>>
     */
    public array $onlineUserIdsByConversation = [];

    public function mount(): void
    {
        $this->loadPage(reset: true);
    }

    #[On('conversation-updated')]
    public function refreshList(): void
    {
        $this->loadPage(reset: true);
    }

    /**
     * @param  list<array{id: int|string, name: string}>  $typingUsers
     */
    #[On('messaging-typing-updated')]
    public function onMessagingTypingUpdated(int $conversationId, array $typingUsers): void
    {
        $this->applyInboxWhisperUpdate('typing', $conversationId, $typingUsers);
    }

    /**
     * @param  list<array{id: int|string, name: string}>  $recordingUsers
     */
    #[On('messaging-recording-updated')]
    public function onMessagingRecordingUpdated(int $conversationId, array $recordingUsers): void
    {
        $this->applyInboxWhisperUpdate('recording', $conversationId, $recordingUsers);
    }

    /**
     * @param  list<int|string>  $onlineUserIds
     */
    #[On('messaging-presence-updated')]
    public function onMessagingPresenceUpdated(int $conversationId, array $onlineUserIds): void
    {
        $normalised = array_values(array_unique(array_filter(array_map(
            static fn ($id): int => (int) $id,
            $onlineUserIds,
        ))));

        if ($normalised === []) {
            unset($this->onlineUserIdsByConversation[$conversationId]);

            return;
        }

        $this->onlineUserIdsByConversation[$conversationId] = $normalised;
    }

    public function loadMore(): void
    {
        if (! $this->hasMore || $this->nextCursor === null) {
            return;
        }

        $this->loadPage(reset: false);
    }

    protected function loadPage(bool $reset): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $cursor = $reset ? null : $this->nextCursor;
        $page = app(ListConversationInboxRows::class)($user, $cursor);

        if ($reset) {
            $this->rows = $page['rows'];
        } else {
            $this->rows = [...$this->rows, ...$page['rows']];
        }

        $this->nextCursor = $page['next_cursor'];
        $this->hasMore = $page['has_more'];
    }

    /**
     * Precompute per-row presentation state (recording/typing/online overlays)
     * so the blade template iterates ready-to-render contexts without any
     * inline `@php` block or repeated array lookups.
     *
     * @return list<array{
     *     row: array<string, mixed>,
     *     cid: int,
     *     recording_names: list<string>,
     *     typing_names: list<string>,
     *     is_recording: bool,
     *     is_typing: bool,
     *     is_other_online: bool,
     * }>
     */
    #[Computed]
    public function rowsWithContext(): array
    {
        $out = [];
        foreach ($this->rows as $row) {
            $cid = (int) $row['conversation_id'];
            $recordingNames = $this->recordingByConversation[$cid] ?? [];
            $typingNames = $this->typingByConversation[$cid] ?? [];
            $isRecording = $recordingNames !== [];
            $isTyping = ! $isRecording && $typingNames !== [];
            $onlineIds = $this->onlineUserIdsByConversation[$cid] ?? [];
            $isOtherOnline = ! (bool) ($row['is_group'] ?? false)
                && array_intersect($row['other_participant_ids'] ?? [], $onlineIds) !== [];

            $out[] = [
                'row' => $row,
                'cid' => $cid,
                'recording_names' => $recordingNames,
                'typing_names' => $typingNames,
                'is_recording' => $isRecording,
                'is_typing' => $isTyping,
                'is_other_online' => $isOtherOnline,
            ];
        }

        return $out;
    }

};
?>

<div class="flex h-full min-h-0 w-full flex-1 flex-col overflow-hidden">
    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
        @forelse ($this->rowsWithContext as $ctx)
            <button
                type="button"
                wire:key="conv-{{ $ctx['cid'] }}"
                wire:click="$parent.selectConversation({{ $ctx['cid'] }})"
                @class([
                    'flex w-full items-start gap-3 border-b border-zinc-100 px-3 py-3 text-left transition hover:bg-zinc-100/80 dark:border-zinc-800 dark:hover:bg-zinc-800/50',
                    'bg-zinc-100 dark:bg-zinc-800' => $selectedConversationId === $ctx['cid'],
                ])
            >
                <div class="relative shrink-0">
                    <flux:avatar
                        :name="$ctx['row']['title']"
                        color="auto"
                        color:seed="{{ $ctx['cid'] }}"
                        size="sm"
                    />
                    @if ($ctx['is_other_online'])
                        <span
                            class="absolute -right-0.5 -bottom-0.5 inline-block size-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-zinc-900"
                            title="{{ __('Online') }}"
                            aria-label="{{ __('Online') }}"
                        ></span>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <flux:text class="truncate font-medium">{{ $ctx['row']['title'] }}</flux:text>
                        @if ($ctx['row']['is_group'])
                            <flux:badge size="sm" color="zinc">{{ __('Group') }}</flux:badge>
                        @endif
                        @if (! empty($ctx['row']['updated_at']))
                            <flux:text size="xs" class="ml-auto shrink-0 text-zinc-400">
                                <x-message.timestamp :iso="$ctx['row']['updated_at']" preset="inbox" />
                            </flux:text>
                        @endif
                    </div>
                    <div class="mt-0.5 flex items-center gap-2">
                        @if ($ctx['is_recording'])
                            <x-whisper.indicator :users="$ctx['recording_names']" variant="recording" scope="inbox" />
                        @elseif ($ctx['is_typing'])
                            <x-whisper.indicator :users="$ctx['typing_names']" variant="typing" scope="inbox" />
                        @elseif ($ctx['row']['subtitle'] !== '')
                            <flux:text size="sm" class="min-w-0 truncate text-zinc-500 dark:text-zinc-400">
                                {{ $ctx['row']['subtitle'] }}
                            </flux:text>
                        @endif
                        @if ($ctx['row']['unread_count'] > 0)
                            <flux:badge size="sm" color="indigo" class="ml-auto shrink-0">
                                {{ $ctx['row']['unread_count'] }}
                            </flux:badge>
                        @endif
                    </div>
                </div>
            </button>
        @empty
            <div class="p-6 text-center">
                <flux:text class="text-zinc-500">{{ __('No conversations yet.') }}</flux:text>
            </div>
        @endforelse

        @if ($hasMore && $nextCursor !== null)
            <div
                class="w-full shrink-0"
                wire:key="conversation-list-sentinel"
                wire:intersect.margin.200px="loadMore"
            >
                <div class="h-2 w-full"></div>
                <flux:text size="sm" class="not-data-loading:hidden py-2 text-center text-zinc-400">
                    {{ __('Loading…') }}
                </flux:text>
            </div>
        @endif
    </div>
</div>
