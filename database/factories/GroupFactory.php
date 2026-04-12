<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessagingGroups\Group;
use Phunky\LaravelMessagingGroups\GroupService;
use Phunky\Models\User;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => ConversationFactory::new(),
            'name' => fake()->words(2, true),
            'avatar' => null,
            'meta' => null,
            'owner_type' => (new User)->getMorphClass(),
            'owner_id' => User::factory(),
        ];
    }

    /**
     * Creates a conversation with the owner as sole participant, then sets group ownership.
     */
    public function withOwner(User $owner): static
    {
        return $this->state(function () use ($owner): array {
            /** @var class-string<Conversation> $conversationClass */
            $conversationClass = config('messaging.models.conversation');
            /** @var class-string<Participant> $participantClass */
            $participantClass = config('messaging.models.participant');

            /** @var Conversation $conversation */
            $conversation = $conversationClass::query()->create([
                'participant_hash' => hash('sha256', 'group:'.Str::uuid()->toString()),
                'meta' => null,
            ]);

            $participantClass::query()->create([
                'conversation_id' => $conversation->getKey(),
                'messageable_type' => $owner->getMorphClass(),
                'messageable_id' => $owner->getKey(),
                'meta' => null,
            ]);

            return [
                'conversation_id' => $conversation->getKey(),
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ];
        });
    }

    /**
     * Invites members after the group exists (owner must be set via {@see withOwner()} or compatible definition).
     *
     * @param  list<User>  $members
     */
    public function withMembers(User ...$members): static
    {
        return $this->afterCreating(function (Group $group) use ($members): void {
            $owner = $group->owner;
            if (! $owner instanceof User) {
                return;
            }

            $groupService = app(GroupService::class);

            foreach ($members as $member) {
                if ($member->is($owner)) {
                    continue;
                }

                $groupService->invite($group, $owner, $member);
            }
        });
    }

    /**
     * Inserts messages from random conversation participants after the group is created.
     */
    public function withMessages(int $count): static
    {
        return $this->afterCreating(function (Group $group) use ($count): void {
            if ($count < 1) {
                return;
            }

            $conversation = $group->conversation;
            /** @var list<int|string> $userIds */
            $userIds = $conversation->participants()
                ->where('messageable_type', (new User)->getMorphClass())
                ->pluck('messageable_id')
                ->all();

            if ($userIds === []) {
                return;
            }

            /** @var Collection<int, User> $members */
            $members = User::query()->whereIn('id', $userIds)->get();
            if ($members->isEmpty()) {
                return;
            }

            $start = now()->subHours(max(1, $count));

            for ($i = 0; $i < $count; $i++) {
                /** @var User $sender */
                $sender = $members->random();
                $sentAt = $start->copy()->addMinutes($i);

                Message::query()->create([
                    'conversation_id' => $conversation->getKey(),
                    'messageable_type' => $sender->getMorphClass(),
                    'messageable_id' => $sender->getKey(),
                    'body' => fake()->realText(random_int(40, 200)),
                    'meta' => null,
                    'sent_at' => $sentAt,
                    'edited_at' => null,
                ]);
            }
        });
    }
}
