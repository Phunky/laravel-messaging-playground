@props([
    'users' => [],
    'variant' => 'typing',
])

@php
    $names = array_values(array_filter(array_map(
        static fn (array $row): string => trim((string) ($row['name'] ?? '')),
        is_array($users) ? $users : [],
    )));

    if ($variant === 'recording') {
        $label = match (count($names)) {
            1 => __(':name is recording a voice note…', ['name' => $names[0] ?? '']),
            2 => __(':a and :b are recording voice notes…', ['a' => $names[0] ?? '', 'b' => $names[1] ?? '']),
            default => __('Several people are recording voice notes…'),
        };
    } else {
        $label = match (count($names)) {
            1 => __(':name is typing…', ['name' => $names[0] ?? '']),
            2 => __(':a and :b are typing…', ['a' => $names[0] ?? '', 'b' => $names[1] ?? '']),
            default => __('Several people are typing…'),
        };
    }
@endphp

<div class="flex justify-start px-4">
    <div class="max-w-[min(85%,36rem)] w-fit">
        <flux:card size="sm" class="relative">
            <div class="flex items-center gap-1.5 text-sm italic text-zinc-500 dark:text-zinc-400" aria-live="polite">
                @if ($variant === 'recording')
                    <flux:icon name="microphone" variant="micro" class="size-4 shrink-0 chat-recording-mic text-red-500" aria-hidden="true" />
                @else
                    <span class="inline-flex shrink-0 gap-0.5" aria-hidden="true">
                        <span class="chat-typing-dot block size-1 rounded-full bg-current" style="animation-delay: 0ms"></span>
                        <span class="chat-typing-dot block size-1 rounded-full bg-current" style="animation-delay: 150ms"></span>
                        <span class="chat-typing-dot block size-1 rounded-full bg-current" style="animation-delay: 300ms"></span>
                    </span>
                @endif
                <span class="truncate">{{ $label }}</span>
            </div>
        </flux:card>
    </div>
</div>
