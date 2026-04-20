@props([
    'users' => [],
    'variant' => 'typing',
    'scope' => 'pane',
])

@php
    $names = array_values(array_filter(array_map(
        static fn ($row): string => trim((string) (($row['name'] ?? $row) ?? '')),
        is_array($users) ? $users : [],
    )));

    $count = count($names);

    if ($variant === 'recording') {
        $label = match ($count) {
            1 => __(':name is recording a voice note…', ['name' => $names[0] ?? '']),
            2 => __(':a and :b are recording voice notes…', ['a' => $names[0] ?? '', 'b' => $names[1] ?? '']),
            default => __('Several people are recording voice notes…'),
        };
        $textColor = $scope === 'inbox'
            ? 'text-sm italic text-red-600 dark:text-red-400'
            : 'text-xs italic text-zinc-500 dark:text-zinc-400';
        $iconColor = $scope === 'inbox' ? '' : 'text-red-500';
    } else {
        $label = match ($count) {
            1 => __(':name is typing…', ['name' => $names[0] ?? '']),
            2 => __(':a and :b are typing…', ['a' => $names[0] ?? '', 'b' => $names[1] ?? '']),
            default => __('Several people are typing…'),
        };
        $textColor = $scope === 'inbox'
            ? 'text-sm italic text-emerald-600 dark:text-emerald-400'
            : 'text-xs italic text-zinc-500 dark:text-zinc-400';
        $iconColor = '';
    }
@endphp

<span
    {{ $attributes->class(['flex min-w-0 items-center gap-1.5', $textColor]) }}
    aria-live="polite"
>
    @if ($variant === 'recording')
        <flux:icon name="microphone" variant="micro" class="size-3.5 shrink-0 chat-recording-mic {{ $iconColor }}" aria-hidden="true" />
    @else
        <span class="inline-flex shrink-0 gap-0.5" aria-hidden="true">
            <span class="chat-typing-dot block size-1 rounded-full bg-current" style="animation-delay: 0ms"></span>
            <span class="chat-typing-dot block size-1 rounded-full bg-current" style="animation-delay: 150ms"></span>
            <span class="chat-typing-dot block size-1 rounded-full bg-current" style="animation-delay: 300ms"></span>
        </span>
    @endif
    <span class="truncate">{{ $label }}</span>
</span>
