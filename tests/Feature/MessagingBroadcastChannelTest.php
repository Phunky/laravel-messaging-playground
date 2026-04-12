<?php

namespace Tests\Feature;

use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\Models\User;
use Tests\TestCase;

class MessagingBroadcastChannelTest extends TestCase
{
    /**
     * Mirrors routes/channels.php: only participants may listen on messaging.conversation.{id}.
     */
    public function test_messaging_channel_membership_allows_only_conversation_participants(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $stranger = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);

        $this->assertTrue($alice->conversations()->whereKey($conversation->getKey())->exists());
        $this->assertTrue($bob->conversations()->whereKey($conversation->getKey())->exists());
        $this->assertFalse($stranger->conversations()->whereKey($conversation->getKey())->exists());
    }
}
