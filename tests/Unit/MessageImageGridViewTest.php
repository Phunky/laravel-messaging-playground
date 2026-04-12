<?php

namespace Tests\Unit;

use Tests\TestCase;

class MessageImageGridViewTest extends TestCase
{
    public function test_shows_plus_overlay_and_last_thumbnail_opens_fourth_attachment(): void
    {
        $items = [];
        for ($i = 1; $i <= 5; $i++) {
            $items[] = [
                'id' => $i * 10,
                'type' => 'image',
                'url' => "https://example.com/{$i}.jpg",
                'filename' => "{$i}.jpg",
                'mime_type' => 'image/jpeg',
            ];
        }

        $html = view('components.chat.message-image-grid', ['items' => $items, 'variant' => 'mine'])->render();

        $this->assertStringContainsString('+1', $html);
        $this->assertStringContainsString('open-media-40-more', $html);
        $this->assertStringContainsString('\u0022attachmentId\u0022:40', $html);
    }

    public function test_six_images_shows_plus_two_overlay_on_fourth_thumbnail(): void
    {
        $items = [];
        for ($i = 1; $i <= 6; $i++) {
            $items[] = [
                'id' => $i,
                'type' => 'image',
                'url' => "https://example.com/{$i}.jpg",
                'filename' => "{$i}.jpg",
                'mime_type' => 'image/jpeg',
            ];
        }

        $html = view('components.chat.message-image-grid', ['items' => $items, 'variant' => 'mine'])->render();

        $this->assertStringContainsString('+2', $html);
        $this->assertStringContainsString('open-media-4-more', $html);
        $this->assertStringContainsString('\u0022attachmentId\u0022:4', $html);
    }

    public function test_four_images_has_no_plus_overlay(): void
    {
        $items = [];
        for ($i = 1; $i <= 4; $i++) {
            $items[] = [
                'id' => $i,
                'type' => 'image',
                'url' => "https://example.com/{$i}.jpg",
                'filename' => "{$i}.jpg",
                'mime_type' => 'image/jpeg',
            ];
        }

        $html = view('components.chat.message-image-grid', ['items' => $items, 'variant' => 'mine'])->render();

        $this->assertStringNotContainsString('+1', $html);
        $this->assertStringNotContainsString('+2', $html);
    }
}
