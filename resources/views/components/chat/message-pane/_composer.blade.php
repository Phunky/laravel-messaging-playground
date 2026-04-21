<div class="shrink-0 w-full pb-4 px-4">
    <div class="mx-auto w-full max-w-4xl">
        <form wire:submit="sendMessage">
            @include('components.chat.message-pane._pending-attachments')
            @error('pendingFiles.*')
                <flux:text size="sm" class="mb-2 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror
            @if ($voiceNoteError !== '')
                <flux:text size="sm" class="mb-2 text-red-600 dark:text-red-400">{{ $voiceNoteError }}</flux:text>
            @endif
            @if ($videoNoteError !== '')
                <flux:text size="sm" class="mb-2 text-red-600 dark:text-red-400">{{ $videoNoteError }}</flux:text>
            @endif

            <div class="w-full">
                <input
                    id="{{ $this->pendingFileInputId }}"
                    type="file"
                    wire:key="pending-files-input-{{ $pendingFilesInputKey }}"
                    class="hidden"
                    wire:model="pendingFiles"
                    @if ($this->attachmentMaxFiles > 1) multiple @endif
                    accept="{{ $attachmentAccept }}"
                />
                <div
                    class="w-full"
                    x-data="chatVoiceNote({
                        conversationId: {{ (int) ($conversationId ?? 0) }},
                        maxVideoRecordSeconds: {{ (int) config('messaging.video_note_max_record_seconds', 60) }},
                        errUnsupported: @js(__('Microphone recording is not supported in this browser.')),
                        errPermission: @js(__('Microphone permission was denied.')),
                        errUpload: @js(__('Could not upload the voice note. Please try again.')),
                        errVideoUnsupported: @js(__('Video recording is not supported in this browser.')),
                        errVideoPermission: @js(__('Camera or microphone permission was denied.')),
                        errVideoUpload: @js(__('Could not upload the video note. Please try again.')),
                    })"
                >
                    @include('components.chat.message-pane._composer-input')
                    @include('components.chat.message-pane._voice-recorder')
                    @include('components.chat.message-pane._video-recorder')
                </div>
            </div>
            @error('newMessage')
                <flux:text size="sm" class="mt-2 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror
        </form>
    </div>
</div>
