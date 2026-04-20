<?php

use Phunky\Models\User;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Phunky\Livewire\Concerns\TracksInboxWhispers;
use Phunky\Support\ConversationListMessagePreview;
use Phunky\LaravelMessagingReactions\Reaction;
use Livewire\Attributes\On;
use Livewire\Component;
use Phunky\LaravelMessagingGroups\Group;
use Phunky\LaravelMessaging\Facades\Messenger;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;

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

        $conversationTable = (new (config('messaging.models.conversation')))->getTable();
        $messagesTable = messaging_table('messages');
        $reactionsTable = messaging_table('reactions');

        $lastActivitySql = <<<SQL
            CASE
                WHEN (SELECT MAX(r.updated_at) FROM {$reactionsTable} r
                      INNER JOIN {$messagesTable} rm ON rm.id = r.message_id
                      WHERE rm.conversation_id = {$conversationTable}.id)
                     > (SELECT MAX(ms.sent_at) FROM {$messagesTable} ms
                        WHERE ms.conversation_id = {$conversationTable}.id)
                THEN (SELECT MAX(r2.updated_at) FROM {$reactionsTable} r2
                      INNER JOIN {$messagesTable} rm2 ON rm2.id = r2.message_id
                      WHERE rm2.conversation_id = {$conversationTable}.id)
                ELSE (SELECT MAX(ms2.sent_at) FROM {$messagesTable} ms2
                      WHERE ms2.conversation_id = {$conversationTable}.id)
            END
            SQL;

        $query = Messenger::conversationsFor($user)
            ->with(['participants.messageable', 'latestMessage.attachments'])
            ->selectSub($lastActivitySql, 'last_activity_at')
            ->reorder()
            ->orderByDesc('last_activity_at')
            ->orderByDesc($conversationTable.'.id');

        $perPage = 20;

        if ($reset) {
            $this->rows = [];
            $page = $query->cursorPaginate($perPage);
        } else {
            $page = $query->cursorPaginate($perPage, ['*'], 'cursor', Cursor::fromEncoded($this->nextCursor));
        }

        $conversations = collect($page->items());
        $ids = $conversations->pluck('id');

        $groups = Group::query()->whereIn('conversation_id', $ids)->get()->keyBy('conversation_id');
        $reactions = $this->loadLastReactions($ids);

        foreach ($conversations as $conversation) {
            if (! $conversation instanceof Conversation) {
                continue;
            }

            $this->rows[] = $this->formatRow(
                $user,
                $conversation,
                $groups->get($conversation->id),
                $reactions->get($conversation->id),
            );
        }

        $this->nextCursor = $page->nextCursor()?->encode();
        $this->hasMore = $page->hasMorePages();
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return Collection<int|string, Reaction>
     */
    protected function loadLastReactions(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $messagesTable = messaging_table('messages');
        $reactionsTable = messaging_table('reactions');

        return Reaction::query()
            ->select("{$reactionsTable}.*")
            ->join($messagesTable, "{$messagesTable}.id", '=', "{$reactionsTable}.message_id")
            ->whereIn("{$messagesTable}.conversation_id", $ids->all())
            ->with(['participant.messageable', 'message'])
            ->orderByDesc("{$reactionsTable}.updated_at")
            ->get()
            ->unique(fn (Reaction $r) => $r->message->conversation_id)
            ->keyBy(fn (Reaction $r) => $r->message->conversation_id);
    }

    protected function formatRow(
        User $user,
        Conversation $conversation,
        ?Group $group,
        ?Reaction $lastReaction = null,
    ): array {
        $title = 'Conversation';
        $subtitle = '';
        $isGroup = $group !== null;

        $otherParticipantIds = $conversation->participants
            ->map(fn ($p) => $p->messageable)
            ->filter()
            ->filter(fn ($m) => $m instanceof User && (string) $m->getKey() !== (string) $user->getKey())
            ->map(fn (User $m) => (int) $m->getKey())
            ->values()
            ->all();

        if ($isGroup) {
            $title = $group->name;
        } else {
            $other = $conversation->participants
                ->map(fn ($p) => $p->messageable)
                ->filter()
                ->first(fn ($m) => $m && (string) $m->getKey() !== (string) $user->getKey());

            if ($other instanceof User) {
                $title = $other->name;
            }
        }

        $lastMessage = $conversation->latestMessage;

        $reactionIsLatest = $lastReaction
            && (! $lastMessage || $lastReaction->updated_at->greaterThan($lastMessage->sent_at));

        if ($reactionIsLatest) {
            $name = $lastReaction->participant->messageable?->name ?? 'Someone';
            $body = Str::limit($lastReaction->message->body, 30);
            $subtitle = "{$name} reacted {$lastReaction->reaction} to \"{$body}\"";
        } elseif ($lastMessage instanceof Message) {
            $subtitle = ConversationListMessagePreview::subtitle($lastMessage);
        }

        $activityAt = $conversation->last_activity_at
            ? Carbon::parse($conversation->last_activity_at)
            : ($lastMessage?->sent_at ?? $conversation->updated_at);

        $updatedIso = $activityAt?->toIso8601String();

        return [
            'conversation_id' => (int) $conversation->id,
            'title' => $title,
            'subtitle' => $subtitle,
            'is_group' => $isGroup,
            'updated_at' => $updatedIso,
            'formatted_time' => $this->formatTimestamp($updatedIso),
            'unread_count' => (int) ($conversation->unread_count ?? 0),
            'other_participant_ids' => $otherParticipantIds,
        ];
    }

    protected function formatTimestamp(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }

        $tz = (string) config('app.timezone');
        $ts = Carbon::parse($iso)->timezone($tz);
        $today = Carbon::now()->timezone($tz)->startOfDay();

        if ($ts->isAfter($today)) {
            return $ts->format('g:i a');
        }

        if ($ts->isAfter($today->copy()->subDay())) {
            return __('Yesterday');
        }

        $sevenDaysAgo = $today->copy()->subDays(7)->startOfDay();
        if ($ts->isAfter($sevenDaysAgo)) {
            return $ts->translatedFormat('l');
        }

        return $ts->format('d/m/Y');
    }

};
?>

<div class="flex h-full min-h-0 w-full flex-1 flex-col overflow-hidden">
    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
        @forelse ($rows as $row)
            @php
                $cid = $row['conversation_id'];
                $recordingNames = $recordingByConversation[$cid] ?? [];
                $isRecording = $recordingNames !== [];
                $typingNames = $typingByConversation[$cid] ?? [];
                $isTyping = ! $isRecording && $typingNames !== [];
                $onlineIds = $onlineUserIdsByConversation[$cid] ?? [];
                $isOtherOnline = ! $row['is_group']
                    && array_intersect($row['other_participant_ids'], $onlineIds) !== [];
            @endphp
            <button
                type="button"
                wire:key="conv-{{ $cid }}"
                wire:click="$parent.selectConversation({{ $cid }})"
                class="flex w-full items-start gap-3 border-b border-zinc-100 px-3 py-3 text-left transition hover:bg-zinc-100/80 dark:border-zinc-800 dark:hover:bg-zinc-800/50 {{ $selectedConversationId === $cid ? 'bg-zinc-100 dark:bg-zinc-800' : '' }}"
            >
                <div class="relative shrink-0">
                    <flux:avatar
                        :name="$row['title']"
                        color="auto"
                        color:seed="{{ $cid }}"
                        size="sm"
                    />
                    @if ($isOtherOnline)
                        <span
                            class="absolute -right-0.5 -bottom-0.5 inline-block size-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-zinc-900"
                            title="{{ __('Online') }}"
                            aria-label="{{ __('Online') }}"
                        ></span>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <flux:text class="truncate font-medium">{{ $row['title'] }}</flux:text>
                        @if ($row['is_group'])
                            <flux:badge size="sm" color="zinc">{{ __('Group') }}</flux:badge>
                        @endif
                        @if ($row['formatted_time'] !== '')
                            <flux:text size="xs" class="ml-auto shrink-0 text-zinc-400">
                                {{ $row['formatted_time'] }}
                            </flux:text>
                        @endif
                    </div>
                    <div class="mt-0.5 flex items-center gap-2">
                        @if ($isRecording)
                            <x-chat.whisper-indicator :users="$recordingNames" variant="recording" scope="inbox" />
                        @elseif ($isTyping)
                            <x-chat.whisper-indicator :users="$typingNames" variant="typing" scope="inbox" />
                        @elseif ($row['subtitle'] !== '')
                            <flux:text size="sm" class="min-w-0 truncate text-zinc-500 dark:text-zinc-400">
                                {{ $row['subtitle'] }}
                            </flux:text>
                        @endif
                        @if ($row['unread_count'] > 0)
                            <flux:badge size="sm" color="indigo" class="ml-auto shrink-0">
                                {{ $row['unread_count'] }}
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
