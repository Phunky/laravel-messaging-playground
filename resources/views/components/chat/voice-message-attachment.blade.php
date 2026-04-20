@props([
    'url' => '',
    'variant' => 'mine',
])

<div
    wire:ignore
    @class([
        'flex w-full min-w-0 items-center gap-2 rounded-lg px-0.5 py-0.5',
        'text-emerald-50' => $variant === 'mine',
        'text-zinc-700 dark:text-zinc-200' => $variant !== 'mine',
    ])
    x-data="chatVoiceAttachment({ audioUrl: @js($url), isMine: @js($variant === 'mine') })"
    role="group"
    aria-label="{{ __('Voice message') }}"
>
    <audio
        x-ref="audio"
        src="{{ $url }}"
        preload="metadata"
        class="hidden"
        controlslist="nodownload noplaybackrate"
    ></audio>

    <div class="flex shrink-0 items-center">
        <flux:button
            type="button"
            size="sm"
            variant="subtle"
            icon="play"
            class="shrink-0"
            x-show="!playing"
            x-cloak
            aria-label="{{ __('Play voice message') }}"
            @click.prevent="togglePlay()"
        />
        <flux:button
            type="button"
            size="sm"
            variant="subtle"
            icon="pause"
            class="shrink-0"
            x-show="playing"
            x-cloak
            aria-label="{{ __('Pause voice message') }}"
            @click.prevent="togglePlay()"
        />
    </div>

    <canvas
        x-ref="canvas"
        class="pointer-events-none h-8 min-h-8 w-full min-w-0 flex-1"
        aria-hidden="true"
    ></canvas>

    <span
        class="w-[5.5rem] shrink-0 tabular-nums text-xs opacity-90"
        x-text="timeLabel"
    ></span>
</div>
