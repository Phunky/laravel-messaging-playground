<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Support\ParticipantHash;
use Phunky\Models\User;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'participant_hash' => hash('sha256', fake()->uuid()),
            'meta' => null,
        ];
    }

    /**
     * Two-person direct message conversation with correct participant hash and rows.
     */
    public function direct(User $a, User $b): static
    {
        return $this->withParticipants($a, $b);
    }

    /**
     * @param  list<User>  $users
     */
    public function withParticipants(User ...$users): static
    {
        return $this->state(fn (): array => [
            'participant_hash' => ParticipantHash::make($users),
        ])->afterCreating(function (Conversation $conversation) use ($users): void {
            foreach ($users as $user) {
                ParticipantFactory::new()->create([
                    'conversation_id' => $conversation->getKey(),
                    'messageable_type' => $user->getMorphClass(),
                    'messageable_id' => $user->getKey(),
                    'meta' => null,
                ]);
            }
        });
    }

    /**
     * @param  list<User>  $senders
     */
    public function withMessages(int $count, array $senders): static
    {
        return $this->afterCreating(function (Conversation $conversation) use ($count, $senders): void {
            if ($count < 1 || $senders === []) {
                return;
            }

            $start = Carbon::now()->subHours(max(1, $count));
            for ($i = 0; $i < $count; $i++) {
                /** @var User $sender */
                $sender = $senders[$i % count($senders)];
                $sentAt = $start->copy()->addMinutes($i);

                MessageFactory::new()->create([
                    'conversation_id' => $conversation->getKey(),
                    'messageable_type' => $sender->getMorphClass(),
                    'messageable_id' => $sender->getKey(),
                    'body' => fake()->realText(random_int(40, 160)),
                    'meta' => null,
                    'sent_at' => $sentAt,
                    'edited_at' => null,
                ]);
            }
        });
    }
}
