<?php

namespace Tests\Unit\Support\Chat;

use Phunky\Support\Chat\VideoPosterSettings;
use Tests\TestCase;

class VideoPosterSettingsTest extends TestCase
{
    public function test_preload_auto_for_video_note_type(): void
    {
        $this->assertSame('auto', VideoPosterSettings::preload('video/mp4', 'video_note'));
    }

    public function test_preload_auto_for_webm_mime(): void
    {
        $this->assertSame('auto', VideoPosterSettings::preload('video/webm', 'video'));
    }

    public function test_preload_metadata_for_otherwise(): void
    {
        $this->assertSame('metadata', VideoPosterSettings::preload('video/mp4', 'video'));
    }
}
