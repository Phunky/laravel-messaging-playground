<?php

namespace Phunky\Support\Chat;

use Illuminate\Support\Carbon;

/**
 * Timezone-aware timestamp formatting for the chat UI. Single source of truth
 * for every `g:i a`, `d/m/Y`, Today/Yesterday, and `H:i` string that used to
 * live inline in blade templates.
 */
final class ChatTimestamp
{
    /**
     * Time-of-day inside a bubble (e.g. "9:05 am"). Returns `''` when `$iso`
     * is null/empty.
     */
    public static function bubbleTime(?string $iso): string
    {
        $parsed = self::parse($iso);

        return $parsed?->format('g:i a') ?? '';
    }

    /**
     * Compact edited marker inside a bubble (e.g. "14:32").
     */
    public static function bubbleEditedTime(?string $iso): string
    {
        $parsed = self::parse($iso);

        return $parsed?->format('H:i') ?? '';
    }

    /**
     * Conversation-list right-rail timestamp: time for today, "Yesterday", the
     * translated weekday name within the last seven days, or `d/m/Y` otherwise.
     */
    public static function inbox(?string $iso): string
    {
        $parsed = self::parse($iso);
        if ($parsed === null) {
            return '';
        }

        $today = self::today();

        if ($parsed->isAfter($today)) {
            return $parsed->format('g:i a');
        }

        if ($parsed->isAfter($today->copy()->subDay())) {
            return __('Yesterday');
        }

        $sevenDaysAgo = $today->copy()->subDays(7)->startOfDay();
        if ($parsed->isAfter($sevenDaysAgo)) {
            return $parsed->translatedFormat('l');
        }

        return $parsed->format('d/m/Y');
    }

    /**
     * Sticky date-separator label: Today, Yesterday, translated weekday for
     * days 2-7 ago, or `d/m/Y`.
     */
    public static function dateSeparator(?string $iso): string
    {
        $parsed = self::parse($iso);
        if ($parsed === null) {
            return '';
        }

        $day = $parsed->copy()->startOfDay();
        $today = self::today();

        if ($day->equalTo($today)) {
            return __('Today');
        }

        if ($day->equalTo($today->copy()->subDay())) {
            return __('Yesterday');
        }

        $sevenDaysAgo = $today->copy()->subDays(7)->startOfDay();
        $twoDaysAgo = $today->copy()->subDays(2)->startOfDay();

        if ($day->betweenIncluded($sevenDaysAgo, $twoDaysAgo)) {
            return $day->translatedFormat('l');
        }

        return $day->format('d/m/Y');
    }

    /**
     * App-timezone YYYY-MM-DD bucket used to group messages by day for date
     * separators.
     */
    public static function dayBucket(?string $iso): ?string
    {
        return self::parse($iso)?->toDateString();
    }

    private static function parse(?string $iso): ?Carbon
    {
        if ($iso === null || $iso === '') {
            return null;
        }

        return Carbon::parse($iso)->timezone(self::timezone());
    }

    private static function today(): Carbon
    {
        return Carbon::now()->timezone(self::timezone())->startOfDay();
    }

    private static function timezone(): string
    {
        return (string) config('app.timezone');
    }
}
