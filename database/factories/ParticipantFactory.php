<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Participant;
use Phunky\Models\User;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
{
    protected $model = Participant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => ConversationFactory::new(),
            'messageable_type' => (new User)->getMorphClass(),
            'messageable_id' => User::factory(),
            'meta' => null,
        ];
    }

    public function forConversation(Conversation $conversation): static
    {
        return $this->state(fn (): array => [
            'conversation_id' => $conversation->getKey(),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'messageable_type' => $user->getMorphClass(),
            'messageable_id' => $user->getKey(),
        ]);
    }
}
