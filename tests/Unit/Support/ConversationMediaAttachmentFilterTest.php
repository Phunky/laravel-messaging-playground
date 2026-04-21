<?php

namespace Tests\Unit\Support;

use Phunky\LaravelMessagingAttachments\Attachment;
use Phunky\Support\ConversationMediaAttachmentFilter;
use Tests\TestCase;

class ConversationMediaAttachmentFilterTest extends TestCase
{
    public function test_video_note_is_viewer_media_slot(): void
    {
        $attachment = new Attachment;
        $attachment->forceFill([
            'type' => 'video_note',
            'mime_type' => 'video/webm',
            'url' => 'https://cdn.test/v.webm',
            'path' => 'messaging/1/1/v.webm',
        ]);

        $this->assertTrue(ConversationMediaAttachmentFilter::isViewerMediaSlot($attachment));
        $this->assertSame('video', ConversationMediaAttachmentFilter::viewerDisplayType($attachment));
    }
}
