<?php

namespace Phunky\Support;

final class DocumentAttachmentIcon
{
    /**
     * Flux icon name for the document preview (Heroicons slug). Configure per MIME
     * and extension in config/messaging.php under document_attachment_icons.
     */
    public static function resolve(?string $mimeType, string $filename): string
    {
        $mime = $mimeType !== null && $mimeType !== '' ? trim($mimeType) : null;
        /** @var array{default?: string, mimes?: array<string, string>, extensions?: array<string, string>} $config */
        $config = config('messaging.document_attachment_icons', []);
        $mimes = $config['mimes'] ?? [];
        $extensions = $config['extensions'] ?? [];
        $default = $config['default'] ?? 'document';

        if ($mime !== null && array_key_exists($mime, $mimes)) {
            return (string) $mimes[$mime];
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext !== '' && array_key_exists($ext, $extensions)) {
            return (string) $extensions[$ext];
        }

        return (string) $default;
    }
}
