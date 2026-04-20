<?php

namespace Tests\Unit\Support\Chat;

use Illuminate\Support\Carbon;
use Phunky\Support\Chat\AttachmentViewModel;
use Phunky\Support\Chat\MessageViewModel;
use Tests\TestCase;

class MessageViewModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Europe/London']);
        Carbon::setTestNow(Carbon::parse('2026-04-20T10:00:00Z'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function raw(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'body' => 'hello',
            'sent_at' => '2026-04-20T08:05:00Z',
            'edited_at' => null,
            'sender_id' => 'abc',
            'sender_name' => 'Alice',
            'is_me' => false,
            'attachments' => [],
        ], $overrides);
    }

    public function test_from_array_hydrates_attachments(): void
    {
        $vm = MessageViewModel::fromArray($this->raw([
            'attachments' => [
                ['id' => 1, 'type' => 'image', 'url' => 'https://x', 'filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 100],
            ],
        ]));

        $this->assertTrue($vm->hasAttachments());
        $this->assertInstanceOf(AttachmentViewModel::class, $vm->attachments[0]);
        $this->assertSame(1, $vm->attachments[0]->id);
    }

    public function test_formatted_timestamps_use_chat_timestamp_helper(): void
    {
        $vm = MessageViewModel::fromArray($this->raw([
            'sent_at' => '2026-04-20T08:05:00Z',
            'edited_at' => '2026-04-20T08:10:00Z',
        ]));

        $this->assertSame('9:05 am', $vm->formattedSentAt());
        $this->assertSame('09:10', $vm->formattedEditedAt());
        $this->assertTrue($vm->isEdited());
    }

    public function test_edited_flag_false_when_edited_at_missing(): void
    {
        $vm = MessageViewModel::fromArray($this->raw(['edited_at' => null]));
        $this->assertFalse($vm->isEdited());
    }

    public function test_day_bucket_reflects_app_timezone(): void
    {
        $vm = MessageViewModel::fromArray($this->raw([
            'sent_at' => '2026-04-20T00:05:00+01:00',
        ]));

        $this->assertSame('2026-04-20', $vm->dayBucket());
    }

    public function test_list_from_array_stamps_is_first_of_day(): void
    {
        $rows = [
            $this->raw(['id' => 1, 'sent_at' => '2026-04-19T09:00:00Z']),
            $this->raw(['id' => 2, 'sent_at' => '2026-04-19T10:00:00Z']),
            $this->raw(['id' => 3, 'sent_at' => '2026-04-20T09:00:00Z']),
        ];

        $list = MessageViewModel::listFromArray($rows);

        $this->assertTrue($list[0]->isFirstOfDay);
        $this->assertFalse($list[1]->isFirstOfDay);
        $this->assertTrue($list[2]->isFirstOfDay);
    }

    public function test_attachment_groups_delegates_to_attachment_view_model(): void
    {
        $vm = MessageViewModel::fromArray($this->raw([
            'attachments' => [
                ['id' => 1, 'type' => 'image', 'url' => 'https://x/a', 'filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 100],
                ['id' => 2, 'type' => 'image', 'url' => 'https://x/b', 'filename' => 'b.jpg', 'mime_type' => 'image/jpeg', 'size' => 100],
                ['id' => 3, 'type' => 'document', 'url' => 'https://x/c', 'filename' => 'c.pdf', 'mime_type' => 'application/pdf', 'size' => 200],
            ],
        ]));

        $groups = $vm->attachmentGroups();

        $this->assertCount(2, $groups);
        $this->assertSame(MessageViewModel::KIND_IMAGES, $groups[0]['kind']);
        $this->assertSame(MessageViewModel::KIND_DOCUMENT, $groups[1]['kind']);
    }

    public function test_wireable_round_trip_preserves_attachments_and_flags(): void
    {
        $vm = MessageViewModel::fromArray($this->raw([
            'attachments' => [
                ['id' => 1, 'type' => 'image', 'url' => 'https://x', 'filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 100],
            ],
        ]));

        $hydrated = MessageViewModel::fromLivewire($vm->toLivewire());

        $this->assertEquals($vm, $hydrated);
        $this->assertCount(1, $hydrated->attachments);
        $this->assertSame(1, $hydrated->attachments[0]->id);
    }
}
