<div
    x-cloak
    x-show="recording"
    class="flex w-full"
    style="display: none;"
>
    <audio
        x-ref="previewAudio"
        class="hidden"
        @loadedmetadata="onPreviewLoaded()"
        @ended="onPreviewEnded()"
    ></audio>

    <div
        class="flex h-10 w-full items-center gap-1.5 rounded-xl border border-zinc-200 bg-zinc-100 px-2 py-0 dark:border-zinc-700 dark:bg-zinc-800/90"
        role="region"
        aria-live="polite"
        aria-label="{{ __('Voice recording') }}"
    >
        <flux:button
            type="button"
            size="sm"
            variant="subtle"
            icon="trash"
            class="shrink-0 text-zinc-600 dark:text-zinc-300"
            x-bind:disabled="processing"
            title="{{ __('Discard recording') }}"
            @click.prevent="discardRecording()"
        />
        <div class="relative z-10 flex shrink-0 items-center">
            <div x-show="paused && !previewListening" class="inline-flex">
                <flux:button
                    type="button"
                    size="sm"
                    variant="subtle"
                    icon="play"
                    class="text-zinc-700 dark:text-zinc-200"
                    x-bind:disabled="processing"
                    title="{{ __('Play recording') }}"
                    @click.prevent="togglePreviewPlayback()"
                />
            </div>
            <div x-show="previewListening" class="inline-flex">
                <flux:button
                    type="button"
                    size="sm"
                    variant="subtle"
                    icon="pause"
                    class="text-zinc-700 dark:text-zinc-200"
                    x-bind:disabled="processing"
                    title="{{ __('Pause playback') }}"
                    @click.prevent="togglePreviewPlayback()"
                />
            </div>
        </div>
        <div
            class="flex h-6 min-w-0 flex-1 items-end justify-center gap-px overflow-hidden rounded-md px-0.5"
            aria-hidden="true"
        >
            <template x-for="(height, index) in waveformBars" :key="index">
                <div
                    class="w-0.5 shrink-0 rounded-full bg-zinc-500 transition-[height] duration-75 dark:bg-zinc-400"
                    :style="`height: ${height}%; min-height: 2px`"
                ></div>
            </template>
        </div>
        <span
            class="w-10 shrink-0 tabular-nums text-xs text-zinc-800 dark:text-zinc-100"
            x-text="formatElapsed()"
        ></span>
        <div class="flex shrink-0 items-center gap-0.5">
            <flux:button
                type="button"
                size="sm"
                variant="subtle"
                icon="pause"
                class="!text-red-600 dark:!text-red-400"
                x-show="!paused"
                x-bind:disabled="processing"
                title="{{ __('Pause recording') }}"
                @click.prevent="togglePause()"
            />
            <flux:button
                type="button"
                size="sm"
                variant="subtle"
                icon="microphone"
                class="!text-red-600 dark:!text-red-400"
                x-show="paused"
                x-bind:disabled="processing"
                title="{{ __('Resume recording') }}"
                @click.prevent="togglePause()"
            />
        </div>
        <flux:button
            type="button"
            size="sm"
            variant="subtle"
            icon="paper-airplane"
            class="shrink-0 !text-emerald-600 hover:!text-emerald-700 dark:!text-emerald-400 dark:hover:!text-emerald-300"
            x-bind:disabled="processing"
            title="{{ __('Send voice note') }}"
            @click.prevent="finishRecording()"
        />
    </div>
</div>
