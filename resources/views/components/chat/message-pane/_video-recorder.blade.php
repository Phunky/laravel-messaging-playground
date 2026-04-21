<template x-teleport="body">
    <div
        x-cloak
        x-show="videoRecording || videoNotePreview"
        class="fixed inset-0 z-[110] flex flex-col bg-zinc-950 text-white"
        style="display: none;"
        role="dialog"
        aria-modal="true"
        aria-labelledby="video-note-modal-title"
        @keydown.escape.window.prevent="(videoRecording || videoNotePreview) && !processing && closeVideoNoteModal()"
    >
        <div
            class="flex shrink-0 items-center justify-between gap-3 border-b border-white/5 bg-zinc-950 px-4 py-3 pt-[max(0.75rem,env(safe-area-inset-top))]"
        >
            <flux:heading id="video-note-modal-title" size="lg" class="text-white">
                {{ __('Video note') }}
            </flux:heading>
            <flux:button
                type="button"
                variant="subtle"
                size="sm"
                icon="x-mark"
                class="shrink-0 text-white hover:bg-white/10"
                x-bind:disabled="processing"
                @click.prevent="closeVideoNoteModal()"
            >
                {{ __('Close') }}
            </flux:button>
        </div>

        {{-- Live camera while recording --}}
        <div
            x-show="videoRecording"
            class="relative flex min-h-0 flex-1 flex-col items-center justify-center bg-zinc-950 bg-[radial-gradient(circle_at_50%_120%,rgba(16,185,129,0.08),transparent_55%)] p-4"
        >
            <x-message.video-note-circle-shell variant="mine" class="shadow-xl shadow-black/50">
                <video
                    x-ref="videoLivePreview"
                    class="size-full object-cover"
                    playsinline
                    muted
                ></video>

                <div
                    class="pointer-events-none absolute start-2.5 top-2.5 z-10 flex items-center gap-1.5 rounded-full bg-black/45 px-2 py-1 backdrop-blur-[2px]"
                    aria-hidden="true"
                >
                    <span class="size-2 shrink-0 animate-pulse rounded-full bg-red-500 ring-2 ring-red-400/50"></span>
                    <span class="text-[0.65rem] font-semibold uppercase tracking-wider text-white">
                        {{ __('Rec') }}
                    </span>
                </div>

                <div
                    class="pointer-events-none absolute bottom-2 left-1/2 z-10 -translate-x-1/2 text-xs font-medium tabular-nums text-white drop-shadow-md"
                    x-text="formatVideoElapsed() + ' / ' + formatVideoMaxClock()"
                ></div>
            </x-message.video-note-circle-shell>
        </div>

        {{-- Recorded clip review (same circular frame as the sent message) --}}
        <div
            x-show="videoNotePreview"
            x-cloak
            class="relative flex min-h-0 flex-1 flex-col items-center justify-center bg-zinc-950 bg-[radial-gradient(circle_at_50%_120%,rgba(16,185,129,0.08),transparent_55%)] p-4"
        >
            <x-message.video-note-circle-shell variant="mine" class="shadow-xl shadow-black/50">
                <video
                    x-ref="videoNotePreviewVideo"
                    x-bind:src="videoNotePreviewUrl"
                    crossorigin="anonymous"
                    playsinline
                    preload="metadata"
                    x-bind:data-mime-type="videoNotePendingFile?.type ?? ''"
                    class="chat-video-poster size-full cursor-pointer object-cover"
                    @click="toggleVideoNotePreviewPlayback"
                    @play="videoNotePreviewPlaying = true"
                    @pause="videoNotePreviewPlaying = false"
                    @ended="videoNotePreviewPlaying = false"
                    @loadedmetadata="videoNotePreviewDurationLabel = formatVideoClock($event.target.duration || 0)"
                    @durationchange="videoNotePreviewDurationLabel = formatVideoClock($event.target.duration || 0)"
                ></video>

                <div
                    x-show="!videoNotePreviewPlaying"
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
                    class="pointer-events-none absolute bottom-2 left-1/2 z-10 -translate-x-1/2 text-xs font-medium tabular-nums text-white drop-shadow-md"
                    x-text="videoNotePreviewDurationLabel"
                ></div>
            </x-message.video-note-circle-shell>
        </div>

        {{-- Footer: recording --}}
        <div
            x-show="videoRecording"
            class="flex shrink-0 flex-col gap-3 border-t border-white/10 bg-zinc-900/95 px-4 py-4 pb-[max(1rem,env(safe-area-inset-bottom))]"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button
                    type="button"
                    variant="danger"
                    icon="trash"
                    x-bind:disabled="processing"
                    @click.prevent="discardVideoRecording()"
                >
                    {{ __('Discard') }}
                </flux:button>
                <flux:button
                    type="button"
                    variant="primary"
                    icon="check"
                    class="min-w-[8rem]"
                    x-bind:disabled="processing"
                    @click.prevent="finishVideoRecording()"
                >
                    {{ __('Done') }}
                </flux:button>
            </div>
            <flux:text size="sm" class="text-center text-zinc-400">
                {{ __('Recording stops automatically at the time limit.') }}
            </flux:text>
        </div>

        {{-- Footer: preview before send --}}
        <div
            x-show="videoNotePreview"
            x-cloak
            class="flex shrink-0 flex-col gap-3 border-t border-white/10 bg-zinc-900/95 px-4 py-4 pb-[max(1rem,env(safe-area-inset-bottom))]"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button
                    type="button"
                    variant="danger"
                    icon="trash"
                    x-bind:disabled="processing"
                    @click.prevent="discardVideoNotePreview()"
                >
                    {{ __('Discard') }}
                </flux:button>
                <flux:button
                    type="button"
                    variant="primary"
                    icon="paper-airplane"
                    class="min-w-[8rem]"
                    x-bind:disabled="processing"
                    @click.prevent="sendVideoNoteFromPreview()"
                >
                    {{ __('Send') }}
                </flux:button>
            </div>
            <flux:text size="sm" class="text-center text-zinc-400">
                {{ __('Review your clip, then send or discard.') }}
            </flux:text>
        </div>
    </div>
</template>
