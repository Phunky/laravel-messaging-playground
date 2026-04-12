<?php

namespace Tests\Feature;

use Database\Seeders\MessagingSeeder;
use Phunky\LaravelMessaging\MessagingEventName;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\MessagingEvent;
use Phunky\LaravelMessagingGroups\Group;
use Phunky\LaravelMessagingReactions\Reaction;
use Phunky\Models\User;
use Tests\TestCase;

class MessagingSeederTest extends TestCase
{
    public function test_messaging_seeder_respects_user_and_group_counts(): void
    {
        MessagingSeeder::withConfig([
            'users' => 4,
            'groups' => 2,
            'messages_per_direct' => 2,
            'members_per_group' => [2, 3],
            'messages_per_group' => [2, 2],
            'reactions_conversation_percent' => 100,
        ])->run();

        $this->assertSame(4, User::query()->count());
        $this->assertSame(2, Group::query()->count());
        $this->assertGreaterThan(0, Message::query()->count());
        $this->assertGreaterThan(0, Reaction::query()->count());
    }

    public function test_messaging_seeder_can_skip_all_reaction_conversations(): void
    {
        MessagingSeeder::withConfig([
            'users' => 4,
            'groups' => 1,
            'messages_per_direct' => 2,
            'members_per_group' => [2, 3],
            'messages_per_group' => [2, 2],
            'reactions_conversation_percent' => 0,
        ])->run();

        $this->assertSame(0, Reaction::query()->count());
    }

    public function test_seeded_reactions_use_varied_timestamps(): void
    {
        MessagingSeeder::withConfig([
            'users' => 4,
            'groups' => 2,
            'messages_per_direct' => 12,
            'members_per_group' => [3, 4],
            'messages_per_group' => [15, 20],
            'reactions_conversation_percent' => 100,
        ])->run();

        $distinctTimestamps = Reaction::query()->pluck('created_at')->unique()->count();

        $this->assertGreaterThanOrEqual(5, Reaction::query()->count());
        $this->assertGreaterThan(3, $distinctTimestamps);
    }

    public function test_test_user_has_one_dm_with_each_other_user(): void
    {
        MessagingSeeder::withConfig([
            'users' => 5,
            'groups' => 0,
            'messages_per_direct' => 1,
        ])->run();

        $testUser = User::query()->where('email', 'test@example.com')->firstOrFail();
        $otherCount = User::query()->where('id', '!=', $testUser->getKey())->count();

        $directConversations = $testUser->conversations()->get();

        $this->assertCount($otherCount, $directConversations);
        foreach ($directConversations as $conversation) {
            $this->assertSame(2, $conversation->participants()->count());
        }
    }

    public function test_test_user_is_participant_in_every_group(): void
    {
        MessagingSeeder::withConfig([
            'users' => 6,
            'groups' => 3,
            'messages_per_direct' => 1,
            'members_per_group' => [3, 4],
            'messages_per_group' => [2, 2],
        ])->run();

        $testUser = User::query()->where('email', 'test@example.com')->firstOrFail();

        foreach (Group::query()->get() as $group) {
            $this->assertTrue(
                $group->conversation->participants()
                    ->where('messageable_type', $testUser->getMorphClass())
                    ->where('messageable_id', $testUser->getKey())
                    ->exists()
            );
        }
    }

    public function test_seeder_marks_some_conversations_as_read(): void
    {
        MessagingSeeder::withConfig([
            'users' => 4,
            'groups' => 1,
            'messages_per_direct' => 5,
            'members_per_group' => [2, 3],
            'messages_per_group' => [5, 5],
            'reactions' => false,
            'unread_incoming_receipts' => [4, 4],
        ])->run();

        $readEvents = MessagingEvent::query()->where('event', MessagingEventName::MessageRead)->count();
        $receivedEvents = MessagingEvent::query()->where('event', MessagingEventName::MessageReceived)->count();
        $unreadGap = $receivedEvents - $readEvents;
        $this->assertGreaterThan(0, $readEvents);
        $this->assertLessThanOrEqual(4, $unreadGap);
        $this->assertGreaterThan($unreadGap, $readEvents);
    }

    public function test_messaging_seeder_can_rerun_to_add_more_messages(): void
    {
        $config = [
            'users' => 4,
            'groups' => 0,
            'messages_per_direct' => 3,
            'reactions' => false,
            'unread_incoming_receipts' => [2, 2],
        ];

        MessagingSeeder::withConfig($config)->run();
        $firstPassMessages = Message::query()->count();

        MessagingSeeder::withConfig($config)->run();
        $this->assertGreaterThan($firstPassMessages, Message::query()->count());

        $this->assertSame(
            1,
            User::query()->where('email', 'test@example.com')->count()
        );
    }
}
