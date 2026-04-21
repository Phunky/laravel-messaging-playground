<?php

namespace Phunky\Support\Chat;

/**
 * Presentation helpers for conversation media viewer rows (message-pane).
 *
 * @phpstan-type ViewerRow array{id?: mixed, type?: mixed, url?: mixed, mime_type?: mixed, filename?: mixed, message_id?: mixed, attachment_type?: mixed}
 */
final class ConversationMediaViewerItem
{
    /**
     * @param  ViewerRow  $item
     */
    public static function videoPosterPreload(array $item): string
    {
        $mime = isset($item['mime_type']) && $item['mime_type'] !== '' ? (string) $item['mime_type'] : null;
        $attachmentType = isset($item['attachment_type']) && $item['attachment_type'] !== '' ? (string) $item['attachment_type'] : null;

        return VideoPosterSettings::preload($mime, $attachmentType);
    }

    /**
     * @param  ViewerRow  $item
     */
    public static function videoPosterDataMimeType(array $item): string
    {
        $mime = isset($item['mime_type']) && $item['mime_type'] !== '' ? (string) $item['mime_type'] : null;

        return VideoPosterSettings::dataMimeTypeAttribute($mime);
    }
}
