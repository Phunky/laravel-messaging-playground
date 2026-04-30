<?php

namespace Tests\Feature\Broadcasting;

use Closure;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Support\Facades\Broadcast;
use Phunky\LaravelMessaging\Events\AllMessagesRead;
use Phunky\LaravelMessaging\Events\MessageDeleted;
use Phunky\LaravelMessaging\Events\MessageEdited;
use Phunky\LaravelMessaging\Events\MessageSent;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessagingAttachments\Attachment;
use Phunky\LaravelMessagingAttachments\Events\AttachmentAttached;
use Phunky\LaravelMessagingAttachments\Events\AttachmentDetached;
use Phunky\LaravelMessagingReactions\Events\ReactionAdded;
use Phunky\LaravelMessagingReactions\Events\ReactionRemoved;
use Phunky\LaravelMessagingReactions\Reaction;
use Phunky\Models\User;
use ReflectionClass;
use Tests\TestCase;

class MessagingChannelAuthTest extends TestCase
{
    /**
     * Invoke the registered closure for the `messaging.conversation.{...}`
     * pattern from routes/channels.php. Tests run against the `null`
     * broadcaster (per phpunit.xml) which doesn't auth HTTP requests, so we
     * reach into the Broadcaster's registered callbacks directly.
     */
    private function invokeMessagingChannelAuthorizer(?User $user, int $conversationId): mixed
    {
        $broadcaster = Broadcast::getFacadeRoot()->driver();

        $reflection = new ReflectionClass(Broadcaster::class);
        $channelsProperty = $reflection->getProperty('channels');
        $channelsProperty->setAccessible(true);

        /** @var array<string, Closure> $channels */
        $channels = $channelsProperty->getValue($broadcaster);

        $pattern = 'messaging.conversation.{conversationId}';
        $this->assertArrayHasKey(
            $pattern,
            $channels,
            'routes/channels.php did not register '.$pattern,
        );

        return $channels[$pattern]($user, $conversationId);
    }

    public function test_authorizer_returns_presence_payload_for_a_participant(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$conversation] = app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        $result = $this->invokeMessagingChannelAuthorizer($alice, (int) $conversation->id);

        $this->assertIsArray($result);
        $this->assertSame(
            ['id' => $alice->getKey(), 'name' => $alice->name],
            $result,
        );
    }

    public function test_authorizer_rejects_a_non_participant(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $stranger = User::factory()->create();
        [$conversation] = app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        $result = $this->invokeMessagingChannelAuthorizer($stranger, (int) $conversation->id);

        $this->assertNull($result);
    }

    public function test_authorizer_rejects_unauthenticated_requests(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$conversation] = app(MessagingService::class)->findOrCreateConversation($alice, $bob);

        $result = $this->invokeMessagingChannelAuthorizer(null, (int) $conversation->id);

        $this->assertNull($result);
    }

    /**
     * Sanity-check that the package ships the channel as a PresenceChannel —
     * guards against accidental regression to PrivateChannel.
     */
    public function test_messaging_events_broadcast_on_a_presence_channel(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $message = $messaging->sendMessage($conversation, $alice, 'hi');

        $event = new MessageSent($message, $conversation);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PresenceChannel::class, $channels[0]);
        $this->assertSame('presence-messaging.conversation.'.$conversation->id, $channels[0]->name);
    }

    /**
     * resources/js/chat-messaging-echo.js subscribes using these literal event
     * names (with a leading dot). If the package renames any of them the
     * reading pane silently stops updating, so lock the contract here.
     */
    public function test_package_events_broadcast_under_the_expected_short_names(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($alice, $bob);
        $message = $messaging->sendMessage($conversation, $alice, 'hi');

        $this->assertSame(MessageSent::BROADCAST_NAME, (new MessageSent($message, $conversation))->broadcastAs());
        $this->assertSame(MessageEdited::BROADCAST_NAME, (new MessageEdited($message, 'hi'))->broadcastAs());
        $this->assertSame(MessageDeleted::BROADCAST_NAME, (new MessageDeleted($message, $conversation))->broadcastAs());
        $this->assertSame(AllMessagesRead::BROADCAST_NAME, (new AllMessagesRead($conversation, $alice, 0))->broadcastAs());

        $reaction = new Reaction;
        $this->assertSame('messaging.reaction.added', (new ReactionAdded($reaction, $message, $alice))->broadcastAs());
        $this->assertSame('messaging.reaction.removed', (new ReactionRemoved($message, $alice, '👍'))->broadcastAs());

        $attachment = new Attachment;
        $this->assertSame('messaging.attachment.attached', (new AttachmentAttached($attachment, $message, $alice))->broadcastAs());
        $this->assertSame('messaging.attachment.detached', (new AttachmentDetached($message, $alice, 1))->broadcastAs());
    }
}
