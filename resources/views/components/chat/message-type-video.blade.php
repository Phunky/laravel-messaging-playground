@props([
    'attachment',
    'variant' => 'mine',
    'messageId' => null,
])

@use('Illuminate\Support\Js')

<div
    @class([
        'relative w-fit min-w-0 overflow-hidden rounded-lg bg-zinc-950 ring-1',
        'ring-emerald-950/15 dark:ring-white/15' => $variant === 'mine',
        'ring-zinc-200/80 dark:ring-zinc-600/80' => $variant !== 'mine',
    ])
>
    <video
        src="{{ $attachment->url }}"
        crossorigin="anonymous"
        controls
        playsinline
        preload="{{ $attachment->videoPosterPreload() }}"
        data-mime-type="{{ $attachment->videoPosterDataMimeType() }}"
        class="chat-video-poster block h-auto max-h-[min(70vh,32rem)] w-auto max-w-[calc(0.6*min(85vw,36rem))] object-contain"
    ></video>
    <div class="pointer-events-none absolute end-2 top-2 z-10 sm:end-3 sm:top-3">
        <flux:button
            type="button"
            x-on:click.stop="Livewire.dispatch('message-pane-open-media-viewer', {{ Js::from($attachment->openMediaPayload($messageId !== null ? (int) $messageId : null)) }})"
            variant="subtle"
            size="xs"
            icon="arrows-pointing-out"
            class="pointer-events-auto !rounded-full !bg-black/55 !text-white shadow-sm hover:!bg-black/75 dark:!bg-white/15 dark:hover:!bg-white/25"
            title="{{ __('Open in media viewer') }}"
        />
    </div>
</div>
