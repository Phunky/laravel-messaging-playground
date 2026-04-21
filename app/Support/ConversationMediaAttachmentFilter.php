<?php

namespace Phunky\Support;

use Phunky\LaravelMessagingAttachments\Attachment as MessageAttachment;

/**
 * Mirrors the grouping rules in {@see resources/views/components/chat/message-attachments.blade.php}
 * for image vs video slots so the conversation media viewer stays consistent with inline bubbles.
 */
final class ConversationMediaAttachmentFilter
{
    public static function hasRenderableSource(MessageAttachment $attachment): bool
    {
        if ($attachment->url !== null && $attachment->url !== '') {
            return true;
        }

        return $attachment->path !== null && $attachment->path !== '';
    }

    public static function isViewerMediaSlot(MessageAttachment $attachment): bool
    {
        if (! MessageAttachmentTypeRegistry::has($attachment->type)) {
            return false;
        }

        if (! self::hasRenderableSource($attachment)) {
            return false;
        }

        if (! in_array($attachment->type, ['image', 'video', 'video_note'], true)) {
            return false;
        }

        $mime = (string) ($attachment->mime_type ?? '');
        $mediaType = $attachment->type;
        $isLegacyVideoAsImage = $mediaType === 'image' && str_starts_with($mime, 'video/');
        $isImageSlot = $mediaType === 'image'
            && ($mime === '' || str_starts_with($mime, 'image/'))
            && ! $isLegacyVideoAsImage;
        $isVideoSlot = (in_array($mediaType, ['video', 'video_note'], true) && ($mime === '' || str_starts_with($mime, 'video/')))
            || $isLegacyVideoAsImage;

        return $isImageSlot || $isVideoSlot;
    }

    /**
     * Display kind in the viewer: {@code image} or {@code video}.
     */
    public static function viewerDisplayType(MessageAttachment $attachment): string
    {
        $mime = (string) ($attachment->mime_type ?? '');
        $isLegacyVideoAsImage = $attachment->type === 'image' && str_starts_with($mime, 'video/');

        if (in_array($attachment->type, ['video', 'video_note'], true) || $isLegacyVideoAsImage) {
            return 'video';
        }

        return 'image';
    }
}
