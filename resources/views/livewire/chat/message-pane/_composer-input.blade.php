<div
    x-show="!recording && !videoRecording && !videoNotePreview"
    class="w-full"
    wire:key="typing-emitter-wrap-{{ (int) ($conversationId ?? 0) }}"
    x-data="chatTypingEmitter({{ (int) ($conversationId ?? 0) }})"
>
    <flux:input
        wire:model.live.debounce.400ms="newMessage"
        x-on:input="ping()"
        x-on:blur="stopNow()"
        placeholder="{{ __('Type a message…') }}"
        class="w-full"
    >
        <x-slot name="iconLeading">
            <flux:dropdown position="top" align="start">
                <flux:button
                    type="button"
                    size="sm"
                    variant="subtle"
                    icon="paper-clip"
                    class="-ms-1 shrink-0"
                />

                <flux:popover class="min-w-44 p-1">
                    @foreach (collect(\Phunky\Support\MessageAttachmentTypeRegistry::definitions())->except(['voice_note', 'video_note']) as $kind => $def)
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            wire:key="attach-kind-{{ $kind }}"
                            @click.prevent="(async () => { await $wire.prepareUpload(@js($kind)); document.getElementById(@js($this->pendingFileInputId)).click() })()"
                        >
                            {{ __($def['label']) }}
                        </button>
                    @endforeach
                </flux:popover>
            </flux:dropdown>
        </x-slot>
        <x-slot name="iconTrailing">
            <div class="-mr-1 flex items-center gap-0.5">
                @if (! $this->hasComposerSendContent)
                    <flux:button
                        type="button"
                        size="sm"
                        variant="subtle"
                        icon="video-camera"
                        class="shrink-0"
                        x-bind:disabled="processing"
                        title="{{ __('Record video note') }}"
                        @click.prevent="toggleVideo()"
                    />
                    <flux:button
                        type="button"
                        size="sm"
                        variant="subtle"
                        icon="microphone"
                        class="shrink-0"
                        x-bind:disabled="processing"
                        title="{{ __('Record voice note') }}"
                        @click.prevent="toggle()"
                    />
                @endif
                @if ($this->hasComposerSendContent)
                    <flux:button
                        type="submit"
                        size="sm"
                        variant="subtle"
                        icon="paper-airplane"
                        class="shrink-0 !text-emerald-600 hover:!text-emerald-700 dark:!text-emerald-400 dark:hover:!text-emerald-300"
                    />
                @endif
            </div>
        </x-slot>
    </flux:input>
</div>
