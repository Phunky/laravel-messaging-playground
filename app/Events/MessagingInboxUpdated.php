<?php

namespace Phunky\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\Models\User;

/**
 * Notifies each participant's private channel so the inbox list can refresh when
 * activity occurs on a conversation they belong to — even when that conversation
 * is not the one currently subscribed on the messaging Echo channel.
 */
final class MessagingInboxUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
    ) {}

    public function broadcastAs(): string
    {
        return 'messaging.inbox.updated';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        /** @var class-string<Conversation> $conversationClass */
        $conversationClass = config('messaging.models.conversation');

        $conversation = $conversationClass::query()
            ->with(['participants.messageable'])
            ->find($this->conversationId);

        if ($conversation === null) {
            return [];
        }

        $channels = [];

        foreach ($conversation->participants as $participant) {
            $messageable = $participant->messageable;
            if ($messageable instanceof User) {
                $channels[] = new PrivateChannel('App.Models.User.'.$messageable->getKey());
            }
        }

        return $channels;
    }

    /**
     * @return array{conversation_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
        ];
    }
}
