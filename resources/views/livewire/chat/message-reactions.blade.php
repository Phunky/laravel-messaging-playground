<?php

use Phunky\Models\User;
use Illuminate\Support\Collection;
use Phunky\LaravelMessagingReactions\Exceptions\ReactionException;
use Phunky\LaravelMessagingReactions\Reaction;
use Phunky\LaravelMessagingReactions\ReactionService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\Participant;
use Phunky\LaravelMessaging\Services\MessagingService;

new class extends Component
{
    public int $messageId;

    public ?int $conversationId = null;

    /**
     * others: bubble on the left, picker sits to the right. mine: bubble on the right, picker sits to the left.
     */
    public string $messageAlignment = 'others';

    public bool $pickerOpen = false;

    public int $reactionCacheBust = 0;

    /** @var list<string> */
    public array $emojiPicker = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

    /** @var list<array{key: string, icon: string}> */
    public array $iconPicker = [
        ['key' => 'hand-thumb-up', 'icon' => 'hand-thumb-up'],
        ['key' => 'heart', 'icon' => 'heart'],
        ['key' => 'face-smile', 'icon' => 'face-smile'],
        ['key' => 'hand-raised', 'icon' => 'hand-raised'],
    ];

    public function toggle(string $reaction, ReactionService $reactions, MessagingService $messaging): void
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

        $message = Message::query()->find($this->messageId);
        if (! $message instanceof Message || (int) $message->conversation_id !== (int) $this->conversationId) {
            return;
        }

        try {
            $participant = $messaging->findParticipant($conversation, $user);
            $existing = $participant
                ? Reaction::query()
                    ->where('message_id', $message->getKey())
                    ->where('participant_id', $participant->getKey())
                    ->value('reaction')
                : null;

            if ($existing === $reaction) {
                $reactions->removeReaction($message, $user);
            } else {
                $reactions->react($message, $user, $reaction);
            }

            $this->dispatch('conversation-updated');
        } catch (ReactionException) {
            // Playground: ignore empty reaction edge cases
        }

        $this->pickerOpen = false;
        $this->reactionCacheBust++;
    }

    #[On('open-message-reaction-picker')]
    public function onOpenMessageReactionPicker(int $messageId): void
    {
        if ($messageId !== $this->messageId) {
            return;
        }

        $this->pickerOpen = true;
    }

    #[On('messaging-remote-reaction-updated')]
    public function onRemoteReactionUpdated(int $conversationId, int $messageId): void
    {
        if ($messageId !== $this->messageId) {
            return;
        }

        if ($this->conversationId !== null && $conversationId !== $this->conversationId) {
            return;
        }

        $this->reactionCacheBust++;
    }

    public function isFluxIconKey(string $reaction): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $reaction);
    }

    public function pickerPositionClasses(): string
    {
        return $this->messageAlignment === 'mine'
            ? 'right-full top-1/2 -translate-y-1/2 mr-2'
            : 'left-full top-1/2 -translate-y-1/2 ml-2';
    }

    /**
     * @return array{summary: Collection<int, array<string, mixed>>, my_reaction: string|null}
     */
    #[Computed]
    public function reactionState(): array
    {
        $this->reactionCacheBust;

        $reactions = app(ReactionService::class);
        $messaging = app(MessagingService::class);

        $message = Message::query()->find($this->messageId);
        if (! $message instanceof Message) {
            return [
                'summary' => collect(),
                'my_reaction' => null,
            ];
        }

        $summaryCollection = $reactions->getReactionSummary($message);

        $allPids = $summaryCollection
            ->pluck('participant_ids')
            ->flatten()
            ->unique()
            ->filter()
            ->values();

        $participants = $allPids->isEmpty()
            ? collect()
            : Participant::query()
                ->whereIn('id', $allPids->all())
                ->with('messageable')
                ->get()
                ->keyBy(fn ($p) => (int) $p->getKey());

        $summary = $summaryCollection->map(function (array $row) use ($participants): array {
            /** @var list<int|string> $pids */
            $pids = $row['participant_ids'];
            $names = collect($pids)->map(function (int|string $participantId) use ($participants): string {
                $key = (int) $participantId;
                $participant = $participants->get($key);
                $m = $participant?->messageable;

                return $m instanceof User ? (string) $m->name : __('Unknown');
            })->all();

            return [
                ...$row,
                'names' => $names,
                'title' => implode(', ', $names),
            ];
        });

        $user = auth()->user();
        $myReaction = null;
        if ($user instanceof User) {
            $conversation = $message->conversation;
            if (! $conversation instanceof Conversation) {
                return [
                    'summary' => $summary,
                    'my_reaction' => null,
                ];
            }
            $participant = $messaging->findParticipant($conversation, $user);
            if ($participant !== null) {
                $myReaction = Reaction::query()
                    ->where('message_id', $message->getKey())
                    ->where('participant_id', $participant->getKey())
                    ->value('reaction');
            }
        }

        return [
            'summary' => $summary,
            'my_reaction' => $myReaction,
        ];
    }
};
?>

<div wire:key="reactions-{{ $messageId }}" class="w-full">
    @if ($this->reactionState['summary']->isNotEmpty())
        <div
            @class([
                'mt-1 flex flex-wrap items-center gap-1',
                'justify-end pe-2' => $messageAlignment === 'mine',
                'justify-start ps-2' => $messageAlignment !== 'mine',
            ])
        >
            @foreach ($this->reactionState['summary'] as $row)
                <button
                    type="button"
                    wire:click="toggle({{ \Illuminate\Support\Js::from($row['reaction']) }})"
                    title="{{ $row['title'] }}"
                    class="inline-flex items-center gap-1 rounded-full border bg-white px-2 py-0.5 text-xs shadow-sm transition-colors hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-800 {{ $this->reactionState['my_reaction'] === $row['reaction'] ? 'border-zinc-900 dark:border-zinc-100' : 'border-zinc-200 dark:border-zinc-700' }}"
                >
                    @if ($this->isFluxIconKey($row['reaction']))
                        <flux:icon name="{{ $row['reaction'] }}" variant="micro" class="size-3.5" />
                    @else
                        <span>{{ $row['reaction'] }}</span>
                    @endif
                    <span class="tabular-nums text-zinc-600 dark:text-zinc-400">{{ $row['count'] }}</span>
                </button>
            @endforeach
        </div>
    @endif

    <div
        @class([
            'pointer-events-auto absolute z-20 transition-opacity',
            $this->pickerPositionClasses(),
            'opacity-100' => $this->pickerOpen,
            'opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100' => ! $this->pickerOpen,
        ])
    >
        <flux:dropdown wire:model="pickerOpen" position="bottom" align="{{ $messageAlignment === 'mine' ? 'end' : 'start' }}">
            <flux:button
                variant="ghost"
                size="xs"
                icon="face-smile"
                icon:variant="outline"
                class="!size-7 !rounded-full border border-zinc-400/60 !bg-transparent !shadow-none dark:border-zinc-500/60"
            />

            <flux:popover class="flex flex-col gap-2 p-2">
                <div class="flex items-center gap-1">
                    @foreach ($emojiPicker as $emoji)
                        <button
                            type="button"
                            wire:click="toggle({{ \Illuminate\Support\Js::from($emoji) }})"
                            class="rounded px-1.5 py-1 text-base hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $this->reactionState['my_reaction'] === $emoji ? 'bg-zinc-100 ring-1 ring-zinc-400 dark:bg-zinc-700 dark:ring-zinc-500' : '' }}"
                        >
                            {{ $emoji }}
                        </button>
                    @endforeach
                </div>

                <flux:separator variant="subtle" />

                <div class="flex items-center gap-1">
                    @foreach ($iconPicker as $item)
                        <button
                            type="button"
                            wire:click="toggle({{ \Illuminate\Support\Js::from($item['key']) }})"
                            class="rounded p-1 hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $this->reactionState['my_reaction'] === $item['key'] ? 'bg-zinc-100 ring-1 ring-zinc-400 dark:bg-zinc-700 dark:ring-zinc-500' : '' }}"
                        >
                            <flux:icon name="{{ $item['icon'] }}" variant="mini" class="size-5" />
                        </button>
                    @endforeach
                </div>
            </flux:popover>
        </flux:dropdown>
    </div>
</div>
