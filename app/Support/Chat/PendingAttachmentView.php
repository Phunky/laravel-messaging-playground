<?php

namespace Phunky\Support\Chat;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Presentation helper for message-pane's composer preview row. Wraps a
 * {@see TemporaryUploadedFile} so blade can read mime/preview state without
 * `@php` blocks or inline `method_exists()` calls.
 */
final readonly class PendingAttachmentView
{
    public function __construct(public TemporaryUploadedFile $file) {}

    public function mime(): string
    {
        return (string) $this->file->getMimeType();
    }

    public function filename(): string
    {
        return (string) $this->file->getClientOriginalName();
    }

    public function url(): string
    {
        return (string) $this->file->temporaryUrl();
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime(), 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime(), 'video/');
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->mime(), 'audio/');
    }

    public function canPreview(): bool
    {
        return method_exists($this->file, 'isPreviewable') && $this->file->isPreviewable();
    }

    public function canPreviewImage(): bool
    {
        return $this->isImage() && $this->canPreview();
    }

    public function canPreviewVideo(): bool
    {
        return $this->isVideo() && $this->canPreview();
    }

    public function canPreviewAudio(): bool
    {
        return $this->isAudio() && $this->canPreview();
    }

    public function isImageOrVideoWithoutPreview(): bool
    {
        return ($this->isImage() || $this->isVideo()) && ! $this->canPreview();
    }
}
