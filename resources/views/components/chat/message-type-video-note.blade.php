@props([
    'attachment',
    'variant' => 'mine',
    'messageId' => null,
])

{{-- Video note: circular clip, inline play, duration; sent time + context menu render in message-card (outside the ring). --}}

@use('Illuminate\Support\Js')

<x-chat.video-note-circle-shell
    x-data="chatVideoNoteInline"
    :variant="$variant"
>
    <video
        x-ref="video"
        src="{{ $attachment->url }}"
        crossorigin="anonymous"
        playsinline
        preload="{{ $attachment->videoPosterPreload() }}"
        data-mime-type="{{ $attachment->videoPosterDataMimeType() }}"
        class="chat-video-poster size-full cursor-pointer object-cover"
        @click="togglePlay"
    ></video>

    <div
        x-show="! playing"
        x-cloak
        class="pointer-events-none absolute inset-0 flex items-center justify-center"
    >
        <span
            class="flex size-14 items-center justify-center rounded-full bg-black/55 text-white shadow-lg"
            aria-hidden="true"
        >
            <svg class="ms-0.5 size-8" viewBox="0 0 24 24" fill="currentColor">
                <path d="M8 5v14l11-7z" />
            </svg>
        </span>
    </div>

    <div
        class="pointer-events-none absolute bottom-2 start-2 z-10 text-xs font-medium tabular-nums text-white drop-shadow-md"
        x-text="durationLabel"
    ></div>

    <div class="pointer-events-none absolute end-2 top-2 z-10 sm:end-2.5 sm:top-2.5">
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
</x-chat.video-note-circle-shell>
