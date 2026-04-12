<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Phunky\LaravelMessaging\MessagingEventName;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\MessagingEvent;

/**
 * @extends Factory<MessagingEvent>
 */
class MessagingEventFactory extends Factory
{
    protected $model = MessagingEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => (new Message)->getMorphClass(),
            'subject_id' => MessageFactory::new(),
            'participant_id' => ParticipantFactory::new(),
            'event' => MessagingEventName::MessageReceived,
            'recorded_at' => now(),
            'meta' => null,
        ];
    }
}
