<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Database\Factories\ConversationFactory;
use Database\Factories\GroupFactory;
use Database\Factories\MessageFactory;
use Database\Factories\ParticipantFactory;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessagingGroups\Group;
use Phunky\Models\User;
use Tests\TestCase;

class MessagingFactoriesTest extends TestCase
{
    public function test_conversation_factory_direct_and_with_messages(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $conversation = ConversationFactory::new()
            ->direct($alice, $bob)
            ->withMessages(5, [$alice, $bob])
            ->create();

        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertSame(2, $conversation->participants()->count());
        $this->assertSame(5, $conversation->messages()->count());
    }

    public function test_message_factory_states(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $conversation = ConversationFactory::new()->direct($alice, $bob)->create();

        $sentAt = Carbon::parse('2024-01-15 12:00:00');
        $message = MessageFactory::new()
            ->inConversation($conversation)
            ->from($alice)
            ->sentAt($sentAt)
            ->create(['body' => 'Hello state']);

        $this->assertSame((int) $conversation->getKey(), (int) $message->conversation_id);
        $this->assertSame((int) $alice->getKey(), (int) $message->messageable_id);
        $this->assertTrue($sentAt->equalTo($message->sent_at));
        $this->assertSame('Hello state', $message->body);
    }

    public function test_participant_factory_states(): void
    {
        $alice = User::factory()->create();
        $conversation = ConversationFactory::new()->create();

        $participant = ParticipantFactory::new()
            ->forConversation($conversation)
            ->forUser($alice)
            ->create();

        $this->assertSame((int) $conversation->getKey(), (int) $participant->conversation_id);
        $this->assertSame((int) $alice->getKey(), (int) $participant->messageable_id);
    }

    public function test_group_factory_with_owner_members_and_messages(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $group = GroupFactory::new()
            ->withOwner($owner)
            ->withMembers($member)
            ->withMessages(4)
            ->create(['name' => 'Test Group']);

        $this->assertInstanceOf(Group::class, $group);
        $this->assertSame('Test Group', $group->name);
        $this->assertSame(2, $group->conversation->participants()->count());
        $this->assertSame(4, Message::query()->where('conversation_id', $group->conversation_id)->count());
    }
}
