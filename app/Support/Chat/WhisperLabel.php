<?php

namespace Phunky\Support\Chat;

/**
 * Builds the translated "{name} is typing…" / "{name} is recording…" labels
 * shared by the inbox row indicator and the in-thread card (voice or video
 * notes use the same presence whisper).
 * The `match(count($names))` branches used to live inline in blade.
 */
final class WhisperLabel
{
    public const VARIANT_TYPING = 'typing';

    public const VARIANT_RECORDING = 'recording';

    /**
     * @param  list<string>  $names
     */
    public static function typing(array $names): string
    {
        return match (count($names)) {
            0 => '',
            1 => __(':name is typing…', ['name' => $names[0]]),
            2 => __(':a and :b are typing…', ['a' => $names[0], 'b' => $names[1]]),
            default => __('Several people are typing…'),
        };
    }

    /**
     * @param  list<string>  $names
     */
    public static function recording(array $names): string
    {
        return match (count($names)) {
            0 => '',
            1 => __(':name is recording…', ['name' => $names[0]]),
            2 => __(':a and :b are recording…', ['a' => $names[0], 'b' => $names[1]]),
            default => __('Several people are recording…'),
        };
    }

    /**
     * @param  list<string>  $names
     */
    public static function for(string $variant, array $names): string
    {
        return $variant === self::VARIANT_RECORDING
            ? self::recording($names)
            : self::typing($names);
    }
}
