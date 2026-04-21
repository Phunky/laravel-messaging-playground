<?php

namespace Tests\Unit\Support\Chat;

use Illuminate\Support\Carbon;
use Phunky\Support\Chat\AttachmentViewModel;
use Phunky\Support\Chat\MessageBubbleLayout;
use Phunky\Support\Chat\MessageCardType;
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

    public function test_attachments_are_exclusively_voice_notes(): void
    {
        $onlyVoice = MessageViewModel::fromArray($this->raw([
            'body' => '',
            'attachments' => [
                ['id' => 1, 'type' => 'voice_note', 'url' => 'https://x/a.webm', 'filename' => 'a.webm', 'mime_type' => 'audio/webm', 'size' => 100],
            ],
        ]));
        $this->assertTrue($onlyVoice->attachmentsAreExclusivelyVoiceNotes());

        $withCaption = MessageViewModel::fromArray($this->raw([
            'body' => 'hi',
            'attachments' => [
                ['id' => 1, 'type' => 'voice_note', 'url' => 'https://x/a.webm', 'filename' => 'a.webm', 'mime_type' => 'audio/webm', 'size' => 100],
            ],
        ]));
        $this->assertTrue($withCaption->attachmentsAreExclusivelyVoiceNotes());

        $mixed = MessageViewModel::fromArray($this->raw([
            'attachments' => [
                ['id' => 1, 'type' => 'voice_note', 'url' => 'https://x/a.webm', 'filename' => 'a.webm', 'mime_type' => 'audio/webm', 'size' => 100],
                ['id' => 2, 'type' => 'image', 'url' => 'https://x/a.jpg', 'filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 100],
            ],
        ]));
        $this->assertFalse($mixed->attachmentsAreExclusivelyVoiceNotes());
    }

    public function test_attachments_are_exclusively_video_notes(): void
    {
        $onlyVn = MessageViewModel::fromArray($this->raw([
            'body' => '',
            'attachments' => [
                ['id' => 1, 'type' => 'video_note', 'url' => 'https://x/n.webm', 'filename' => 'n.webm', 'mime_type' => 'video/webm', 'size' => 100],
            ],
        ]));
        $this->assertTrue($onlyVn->attachmentsAreExclusivelyVideoNotes());
        $this->assertTrue($onlyVn->showVideoNoteInlineMeta());

        $vnWithCaption = MessageViewModel::fromArray($this->raw([
            'body' => 'look',
            'attachments' => [
                ['id' => 1, 'type' => 'video_note', 'url' => 'https://x/n.webm', 'filename' => 'n.webm', 'mime_type' => 'video/webm', 'size' => 100],
            ],
        ]));
        $this->assertTrue($vnWithCaption->attachmentsAreExclusivelyVideoNotes());
        $this->assertFalse($vnWithCaption->showVideoNoteInlineMeta());

        $mixed = MessageViewModel::fromArray($this->raw([
            'attachments' => [
                ['id' => 1, 'type' => 'video_note', 'url' => 'https://x/n.webm', 'filename' => 'n.webm', 'mime_type' => 'video/webm', 'size' => 100],
                ['id' => 2, 'type' => 'image', 'url' => 'https://x/a.jpg', 'filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 100],
            ],
        ]));
        $this->assertFalse($mixed->attachmentsAreExclusivelyVideoNotes());

        $textOnly = MessageViewModel::fromArray($this->raw(['attachments' => []]));
        $this->assertFalse($textOnly->attachmentsAreExclusivelyVideoNotes());
    }

    public function test_bubble_layout_text_only_attachments_only_and_captioned(): void
    {
        $textOnly = MessageViewModel::fromArray($this->raw([
            'body' => 'hi',
            'attachments' => [],
        ]));
        $this->assertSame(MessageBubbleLayout::TextOnly, $textOnly->bubbleLayout());

        $attachmentsOnly = MessageViewModel::fromArray($this->raw([
            'body' => '',
            'attachments' => [
                ['id' => 1, 'type' => 'image', 'url' => 'https://x', 'filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 100],
            ],
        ]));
        $this->assertSame(MessageBubbleLayout::AttachmentsOnly, $attachmentsOnly->bubbleLayout());

        $captioned = MessageViewModel::fromArray($this->raw([
            'body' => 'caption',
            'attachments' => [
                ['id' => 1, 'type' => 'image', 'url' => 'https://x', 'filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 100],
            ],
        ]));
        $this->assertSame(MessageBubbleLayout::Captioned, $captioned->bubbleLayout());
    }

    public function test_message_card_type_routes_standard_bubble_vs_video_note_tray(): void
    {
        $textOnly = MessageViewModel::fromArray($this->raw([
            'body' => 'hi',
            'attachments' => [],
        ]));
        $this->assertSame(MessageCardType::StandardBubble, $textOnly->cardType());
        $this->assertFalse($textOnly->usesStandardBubbleVoiceWidthFloor());

        $voiceOnly = MessageViewModel::fromArray($this->raw([
            'body' => '',
            'attachments' => [
                ['id' => 1, 'type' => 'voice_note', 'url' => 'https://x/a.webm', 'filename' => 'a.webm', 'mime_type' => 'audio/webm', 'size' => 100],
            ],
        ]));
        $this->assertSame(MessageCardType::StandardBubble, $voiceOnly->cardType());
        $this->assertTrue($voiceOnly->usesStandardBubbleVoiceWidthFloor());

        $videoNoteOnly = MessageViewModel::fromArray($this->raw([
            'body' => '',
            'attachments' => [
                ['id' => 1, 'type' => 'video_note', 'url' => 'https://x/n.webm', 'filename' => 'n.webm', 'mime_type' => 'video/webm', 'size' => 100],
            ],
        ]));
        $this->assertSame(MessageCardType::VideoNoteTray, $videoNoteOnly->cardType());
        $this->assertFalse($videoNoteOnly->usesStandardBubbleVoiceWidthFloor());
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
            'read_receipt_display' => 'read',
        ]));

        $hydrated = MessageViewModel::fromLivewire($vm->toLivewire());

        $this->assertEquals($vm, $hydrated);
        $this->assertCount(1, $hydrated->attachments);
        $this->assertSame(1, $hydrated->attachments[0]->id);
        $this->assertSame('read', $hydrated->readReceiptDisplay);
    }

    public function test_read_receipt_display_invalid_value_falls_back_to_hidden(): void
    {
        $vm = MessageViewModel::fromArray($this->raw([
            'read_receipt_display' => 'bogus',
        ]));

        $this->assertSame('hidden', $vm->readReceiptDisplay);
    }
}
