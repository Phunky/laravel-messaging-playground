<?php

namespace Database\Factories;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\Models\User;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => ConversationFactory::new(),
            'messageable_type' => (new User)->getMorphClass(),
            'messageable_id' => User::factory(),
            'body' => fake()->realText(120),
            'meta' => null,
            'sent_at' => now(),
            'edited_at' => null,
        ];
    }

    public function inConversation(Conversation $conversation): static
    {
        return $this->state(fn (): array => [
            'conversation_id' => $conversation->getKey(),
        ]);
    }

    public function from(User $sender): static
    {
        return $this->state(fn (): array => [
            'messageable_type' => $sender->getMorphClass(),
            'messageable_id' => $sender->getKey(),
        ]);
    }

    public function sentAt(DateTimeInterface|Carbon $time): static
    {
        return $this->state(fn (): array => [
            'sent_at' => $time instanceof Carbon ? $time : Carbon::instance($time),
        ]);
    }
}
