<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Factories\ConversationFactory;
use Database\Factories\MessageFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Phunky\LaravelMessaging\MessagingEventName;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\MessagingEvent;
use Phunky\LaravelMessaging\Support\ParticipantHash;
use Phunky\LaravelMessagingGroups\Group;
use Phunky\LaravelMessagingGroups\GroupService;
use Phunky\LaravelMessagingReactions\Reaction;
use Phunky\Models\User;

/**
 * Seeds a modest default dataset. Safe to run multiple times to add more messages: the account
 * test@example.com is reused (firstOrCreate), the peer pool tops up from existing users before
 * creating new ones, and direct conversations are resolved by participant hash so DMs append to
 * existing threads instead of creating duplicates.
 */
class MessagingSeeder extends Seeder
{
    /** @var list<int> */
    protected array $messageIds = [];

    protected Carbon $windowStart;

    protected Carbon $windowEnd;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [
        /** Total users in the pool (including the fixed test account). */
        'users' => 12,
        'messages_per_direct' => [6, 14],
        'groups' => 3,
        'members_per_group' => [4, 8],
        'messages_per_group' => [14, 28],
        'reactions' => true,
        /** 0–100: share of conversations that get seeded reactions (stable per conversation id). */
        'reactions_conversation_percent' => 55,
        /** Rough fraction of messages (per included conversation) that may receive reactions. */
        'reactions_message_fraction' => 0.12,
        /**
         * How many *incoming* messages (from someone else) stay without a `message.read` event for the recipient after seeding.
         * Most pairs get `message.received` + `message.read`; only this many incoming pairs stay read-pending.
         *
         * @var int|array{0: int, 1: int}
         */
        'unread_incoming_receipts' => [3, 8],
    ];

    public static function withConfig(array $overrides): static
    {
        $instance = new static;
        $instance->config = array_merge($instance->config, $overrides);

        return $instance;
    }

    public function run(): void
    {
        $this->windowStart = Carbon::now()->subMonths(3);
        $this->windowEnd = Carbon::now()->subMinutes(2);

        $userCount = (int) $this->config['users'];
        if ($userCount < 1) {
            throw new InvalidArgumentException('Config users must be at least 1.');
        }

        /** @var User $testUser */
        $testUser = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        $peerTarget = max(0, $userCount - 1);

        /** @var Collection<int, User> $existingPeers */
        $existingPeers = User::query()
            ->whereKeyNot($testUser->getKey())
            ->orderBy('id')
            ->limit($peerTarget)
            ->get();

        $shortfall = $peerTarget - $existingPeers->count();
        $newPeers = $shortfall > 0
            ? User::factory()->count($shortfall)->create()
            : collect();

        /** @var Collection<int, User> $users */
        $users = $existingPeers->concat($newPeers)->prepend($testUser)->values();

        $groupService = app(GroupService::class);

        $this->seedDirectConversationsWithTestUser($testUser, $users);
        $this->seedGroups($users, $groupService, $testUser);
        $this->applySampleMessagingEvents();
    }

    /**
     * @param  int|array{0: int, 1: int}  $value
     */
    protected function randomInRange(int|array $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return random_int((int) $value[0], (int) $value[1]);
    }

    /**
     * @return list<Carbon>
     */
    protected function randomOrderedTimestamps(int $count, Carbon $from, Carbon $to): array
    {
        if ($count < 1) {
            return [];
        }

        $span = max(1, abs($from->diffInSeconds($to)));
        $offsets = [];

        for ($i = 0; $i < $count; $i++) {
            $offsets[] = random_int(0, $span);
        }

        sort($offsets);

        return array_map(static fn (int $s): Carbon => $from->copy()->addSeconds($s), $offsets);
    }

    protected function reactionsConversationPercent(): int
    {
        /** Legacy tuple overrides the default percent when {@see withConfig()} does not set `reactions_conversation_percent`. */
        if (array_key_exists('reactions_conversation_sample_ratio', $this->config)) {
            /** @var array{0: float, 1: float} $tuple */
            $tuple = $this->config['reactions_conversation_sample_ratio'];

            return max(0, min(100, (int) round((($tuple[0] + $tuple[1]) / 2) * 100)));
        }

        return max(0, min(100, (int) ($this->config['reactions_conversation_percent'] ?? 55)));
    }

    protected function reactionsMessageFraction(): float
    {
        if (array_key_exists('reactions_message_sample_ratio', $this->config)) {
            /** @var array{0: float, 1: float} $tuple */
            $tuple = $this->config['reactions_message_sample_ratio'];

            return max(0.0, min(1.0, ($tuple[0] + $tuple[1]) / 2));
        }

        return max(0.0, min(1.0, (float) ($this->config['reactions_message_fraction'] ?? 0.12)));
    }

    protected function randomReactionTimestamp(Message $message): Carbon
    {
        $start = $message->sent_at?->copy()
            ?? ($message->created_at?->copy() ?? $this->windowStart->copy());

        if ($start->lessThan($this->windowStart)) {
            $start = $this->windowStart->copy();
        }

        $end = $this->windowEnd->copy();

        if ($end->lessThanOrEqualTo($start)) {
            return $start->copy();
        }

        $spanSeconds = (int) floor(abs((float) $start->diffInSeconds($end)));

        if ($spanSeconds < 1) {
            $micros = max(1, (int) round(abs($start->floatDiffInSeconds($end)) * 1_000_000));

            return $start->copy()->addMicroseconds(random_int(1, $micros));
        }

        return $start->copy()
            ->addSeconds(random_int(0, $spanSeconds))
            ->addMilliseconds(random_int(0, 999));
    }

    /**
     * One DM per other user with the test user.
     *
     * @param  Collection<int, User>  $allUsers
     */
    protected function seedDirectConversationsWithTestUser(User $testUser, Collection $allUsers): void
    {
        $messagesPerDirect = $this->config['messages_per_direct'] ?? 30;
        $peers = $allUsers->filter(fn (User $u): bool => ! $u->is($testUser));

        foreach ($peers as $peer) {
            $uLeft = (int) $testUser->getKey() < (int) $peer->getKey() ? $testUser : $peer;
            $uRight = (int) $testUser->getKey() < (int) $peer->getKey() ? $peer : $testUser;

            $hash = ParticipantHash::make([$uLeft, $uRight]);
            /** @var Conversation $conversation */
            $conversation = Conversation::query()->where('participant_hash', $hash)->first()
                ?? ConversationFactory::new()->direct($uLeft, $uRight)->create();

            $turns = $this->randomInRange($messagesPerDirect);
            $timeline = $this->randomOrderedTimestamps($turns, $this->windowStart, $this->windowEnd);
            $pair = collect([$testUser, $peer]);
            $messageIds = [];

            for ($i = 0; $i < $turns; $i++) {
                /** @var User $sender */
                $sender = $pair->random();

                /** @var Message $message */
                $message = MessageFactory::new()->create([
                    'conversation_id' => $conversation->getKey(),
                    'messageable_type' => $sender->getMorphClass(),
                    'messageable_id' => $sender->getKey(),
                    'body' => fake()->realText(random_int(40, 160)),
                    'meta' => null,
                    'sent_at' => $timeline[$i],
                    'edited_at' => null,
                ]);

                $messageIds[] = (int) $message->getKey();
            }

            $this->seedReactionsForConversation($conversation, $messageIds);
            $this->messageIds = array_merge($this->messageIds, $messageIds);
        }
    }

    /**
     * @param  Collection<int, User>  $users
     */
    protected function seedGroups(Collection $users, GroupService $groupService, User $testUser): void
    {
        $groupCount = (int) $this->config['groups'];
        if ($groupCount < 1 || $users->isEmpty()) {
            return;
        }

        $membersPerGroup = $this->config['members_per_group'] ?? 8;
        $messagesPerGroup = $this->config['messages_per_group'] ?? 100;

        for ($i = 0; $i < $groupCount; $i++) {
            $owner = $users->random();
            $group = $groupService->create($owner, 'Group '.($i + 1).' — '.fake()->words(2, true), null, ['seed' => true]);

            $targetSize = max(2, $this->randomInRange($membersPerGroup));
            $members = collect([$owner]);

            if (! $owner->is($testUser)) {
                $groupService->invite($group, $owner, $testUser);
                $members->push($testUser);
            }

            $pool = $users->filter(fn (User $u): bool => ! $members->contains(fn (User $m): bool => $m->is($u)));

            foreach ($pool->shuffle() as $invitee) {
                if ($members->count() >= $targetSize) {
                    break;
                }

                $groupService->invite($group, $owner, $invitee);
                $members->push($invitee);
            }

            $memberIds = $group->fresh(['conversation'])->conversation->participants()
                ->where('messageable_type', (new User)->getMorphClass())
                ->pluck('messageable_id');

            $groupMembers = User::query()->whereIn('id', $memberIds)->get();

            if ($groupMembers->isEmpty()) {
                $groupMembers = collect([$owner]);
            }

            $this->seedGroupMessagesBulk($group, $groupMembers, $this->randomInRange($messagesPerGroup));
        }
    }

    /**
     * @param  Collection<int, User>  $members
     */
    protected function seedGroupMessagesBulk(Group $group, Collection $members, int $count): void
    {
        $conversation = $group->conversation;
        $before = (int) (Message::query()->max('id') ?? 0);
        $timeline = $this->randomOrderedTimestamps($count, $this->windowStart, $this->windowEnd);

        $rows = [];
        $now = Carbon::now();

        for ($i = 0; $i < $count; $i++) {
            $sender = $members->random();

            $row = MessageFactory::new()->make([
                'conversation_id' => $conversation->getKey(),
                'messageable_type' => $sender->getMorphClass(),
                'messageable_id' => $sender->getKey(),
                'body' => fake()->realText(random_int(40, 200)),
                'meta' => null,
                'sent_at' => $timeline[$i],
                'edited_at' => null,
            ])->getAttributes();

            $row['meta'] = null;
            $row['deleted_at'] = null;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            $rows[] = $row;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Message::query()->insert($chunk);
        }

        $messageIds = Message::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('id', '>', $before)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $this->seedReactionsForConversation($conversation, $messageIds);
        $this->messageIds = array_merge($this->messageIds, $messageIds);
    }

    /**
     * @param  list<int>  $messageIds
     */
    protected function seedReactionsForConversation(Conversation $conversation, array $messageIds): void
    {
        if (($this->config['reactions'] ?? true) !== true || $messageIds === []) {
            return;
        }

        $pct = $this->reactionsConversationPercent();
        if ($pct <= 0) {
            return;
        }

        if ($pct < 100) {
            $h = crc32('messaging-seeder-reactions-conv:'.(string) $conversation->getKey());
            if (($h % 100) >= $pct) {
                return;
            }
        }

        $participants = $conversation->participants()
            ->where('messageable_type', (new User)->getMorphClass())
            ->get();

        if ($participants->isEmpty()) {
            return;
        }

        $fraction = $this->reactionsMessageFraction();
        $sampleCount = (int) round(count($messageIds) * $fraction);

        if ($sampleCount === 0 && count($messageIds) > 0) {
            $sampleCount = min(1, count($messageIds));
        }

        $picked = collect($messageIds)->shuffle()->take(min($sampleCount, count($messageIds)));

        $emojiPool = ['👍', '❤️', '😂', '😮', '🙏'];
        $rows = [];

        foreach ($picked as $messageId) {
            $message = Message::query()->find($messageId);
            if (! $message) {
                continue;
            }

            $n = random_int(1, min(4, max(1, $participants->count())));
            $chosenParticipants = $participants->shuffle()->take($n);

            $senderId = (int) $message->messageable_id;
            $senderMorph = $message->messageable_type;

            foreach ($chosenParticipants as $participant) {
                $uid = (int) $participant->messageable_id;

                if ($uid === $senderId && $participant->messageable_type === $senderMorph && random_int(0, 2) !== 0) {
                    continue;
                }

                $reactedAt = $this->randomReactionTimestamp($message);

                $rows[] = [
                    'message_id' => $messageId,
                    'participant_id' => $participant->getKey(),
                    'reaction' => $emojiPool[array_rand($emojiPool)],
                    'created_at' => $reactedAt,
                    'updated_at' => $reactedAt,
                ];
            }
        }

        $rows = collect($rows)
            ->unique(fn (array $r): string => $r['message_id'].'-'.$r['participant_id'])
            ->values()
            ->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            Reaction::query()->insert($chunk);
        }
    }

    /**
     * Insert `message.received` / `message.read` rows for seeded messages so the inbox looks realistic,
     * leaving a small random set of *incoming* pairs without `message.read` so some threads show unread.
     */
    protected function applySampleMessagingEvents(): void
    {
        if ($this->messageIds === []) {
            return;
        }

        $messageMorph = (new Message)->getMorphClass();
        $userMorph = (new User)->getMorphClass();
        $now = Carbon::now();
        $desiredUnread = $this->randomInRange($this->config['unread_incoming_receipts'] ?? [3, 8]);

        $messages = Message::query()
            ->whereIn('id', $this->messageIds)
            ->with(['conversation.participants'])
            ->get();

        /** @var list<array{0: int, 1: int}> $incomingPairs */
        $incomingPairs = [];

        foreach ($messages as $message) {
            $conversation = $message->conversation;
            if (! $conversation) {
                continue;
            }

            foreach ($conversation->participants as $participant) {
                if ($participant->messageable_type !== $userMorph) {
                    continue;
                }

                if (
                    (int) $participant->messageable_id === (int) $message->messageable_id
                    && $participant->messageable_type === $message->messageable_type
                ) {
                    continue;
                }

                $incomingPairs[] = [(int) $message->getKey(), (int) $participant->getKey()];
            }
        }

        $unreadKeys = collect($incomingPairs)
            ->shuffle()
            ->take(min($desiredUnread, max(0, count($incomingPairs))))
            ->mapWithKeys(fn (array $pair): array => [$pair[0].'-'.$pair[1] => true]);

        $eventTable = (new MessagingEvent)->getTable();
        $rows = [];

        foreach ($messages as $message) {
            $conversation = $message->conversation;
            if (! $conversation) {
                continue;
            }

            foreach ($conversation->participants as $participant) {
                $pairKey = (string) (int) $message->getKey().'-'.(int) $participant->getKey();
                $isUnreadIncoming = isset($unreadKeys[$pairKey]);

                $rows[] = [
                    'subject_type' => $messageMorph,
                    'subject_id' => $message->getKey(),
                    'participant_id' => $participant->getKey(),
                    'event' => MessagingEventName::MessageReceived,
                    'recorded_at' => $now,
                    'meta' => null,
                ];

                if (! $isUnreadIncoming) {
                    $rows[] = [
                        'subject_type' => $messageMorph,
                        'subject_id' => $message->getKey(),
                        'participant_id' => $participant->getKey(),
                        'event' => MessagingEventName::MessageRead,
                        'recorded_at' => $now,
                        'meta' => null,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($eventTable)->insert($chunk);
        }
    }
}
