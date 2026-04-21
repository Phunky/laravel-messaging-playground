<?php

namespace Phunky\Support\Chat;

/**
 * Shared rules for {@see chat-video-poster.js}: when to use eager preload so a
 * decoded frame exists for canvas poster capture.
 */
final class VideoPosterSettings
{
    public static function preload(?string $mimeType, ?string $attachmentType): string
    {
        if ($attachmentType === 'video_note') {
            return 'auto';
        }

        $m = strtolower((string) $mimeType);

        if (str_contains($m, 'webm') || str_contains($m, 'matroska')) {
            return 'auto';
        }

        return 'metadata';
    }

    public static function dataMimeTypeAttribute(?string $mimeType): string
    {
        return $mimeType ?? '';
    }
}
