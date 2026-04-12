<?php

namespace Phunky\Actions\Chat;

use Illuminate\Support\Facades\Storage;
use Phunky\LaravelMessagingAttachments\Attachment as MessageAttachment;

final class ResolveAttachmentDisplayUrl
{
    public function __invoke(MessageAttachment $attachment): string
    {
        if ($attachment->url !== null && $attachment->url !== '') {
            return $attachment->url;
        }

        $diskName = $attachment->disk ?? config('messaging.media_disk');
        $disk = Storage::disk($diskName);

        try {
            return $disk->temporaryUrl($attachment->path, now()->addMinutes(60));
        } catch (\RuntimeException|\InvalidArgumentException) {
            return $disk->url($attachment->path);
        }
    }
}
