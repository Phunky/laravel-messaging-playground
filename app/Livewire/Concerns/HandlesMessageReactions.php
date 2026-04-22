<?php

namespace Phunky\Livewire\Concerns;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Phunky\Actions\Chat\ToggleMessageReaction;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\Participant;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessagingReactions\Reaction;
use Phunky\LaravelMessagingReactions\ReactionService;
use Phunky\Models\User;

trait HandlesMessageReactions
{
    public int $messageId;

    public ?int $conversationId = null;

    /**
     * others: bubble on the left, picker sits to the right. mine: bubble on the right, picker sits to the left.
     */
    public string $messageAlignment = 'others';

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

        $result = app(ToggleMessageReaction::class)(
            $user,
            (int) $this->conversationId,
            (int) $this->messageId,
            $reaction,
            $reactions,
            $messaging,
        );

        if ($result['ok']) {
            $this->dispatch('conversation-updated');
        }

        if (property_exists($this, 'pickerOpen')) {
            $this->pickerOpen = false;
        }

        $this->reactionCacheBust++;
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
}
