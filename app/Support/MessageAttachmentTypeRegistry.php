<?php

namespace Phunky\Support;

use InvalidArgumentException;

class MessageAttachmentTypeRegistry
{
    /**
     * @return array<string, array{label: string, accept: string, rules: list<string|object>, max_files: int}>
     */
    public static function definitions(): array
    {
        return config('messaging.media_attachment_types', []);
    }

    public static function defaultKind(): string
    {
        $keys = array_keys(self::definitions());

        return $keys[0] ?? 'image';
    }

    public static function has(string $kind): bool
    {
        return array_key_exists($kind, self::definitions());
    }

    public static function accept(string $kind): string
    {
        if (! self::has($kind)) {
            throw new InvalidArgumentException("Unknown message attachment kind [{$kind}].");
        }

        return self::definitions()[$kind]['accept'];
    }

    /**
     * @return array<string, list<string|object>>
     */
    public static function validationRules(string $kind): array
    {
        if (! self::has($kind)) {
            throw new InvalidArgumentException("Unknown message attachment kind [{$kind}].");
        }

        $def = self::definitions()[$kind];
        $maxFiles = (int) $def['max_files'];

        return [
            'pendingFiles' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            'pendingFiles.*' => $def['rules'],
        ];
    }
}
