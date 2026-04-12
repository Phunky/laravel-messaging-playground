<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use Phunky\Events\MessagingInboxUpdated;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\Models\User;
use Tests\TestCase;

class MessagingInboxBroadcastTest extends TestCase
{
    public function test_messaging_inbox_updated_targets_each_participant_private_user_channel(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        $event = new MessagingInboxUpdated((int) $conversation->getKey());
        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $names = array_map(fn ($c) => $c->name, $channels);
        $this->assertContains('private-App.Models.User.'.$alice->getKey(), $names);
        $this->assertContains('private-App.Models.User.'.$bob->getKey(), $names);
        $this->assertSame('messaging.inbox.updated', $event->broadcastAs());
        $this->assertSame(
            ['conversation_id' => (int) $conversation->getKey()],
            $event->broadcastWith(),
        );
    }

    #[DoesNotPerformAssertions]
    public function test_messaging_inbox_updated_broadcasts_via_null_driver_without_error(): void
    {
        config(['broadcasting.default' => 'null']);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        broadcast(new MessagingInboxUpdated((int) $conversation->getKey()));
    }
}
