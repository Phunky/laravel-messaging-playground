<div class="shrink-0 w-full pb-4 px-4">
    <div class="mx-auto w-full max-w-4xl">
        <form wire:submit="sendMessage">
            @include('livewire.chat.message-pane._pending-attachments')
            @error('pendingFiles.*')
                <flux:text size="sm" class="mb-2 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror
            @if ($voiceNoteError !== '')
                <flux:text size="sm" class="mb-2 text-red-600 dark:text-red-400">{{ $voiceNoteError }}</flux:text>
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
                        errUnsupported: @js(__('Microphone recording is not supported in this browser.')),
                        errPermission: @js(__('Microphone permission was denied.')),
                        errUpload: @js(__('Could not upload the voice note. Please try again.')),
                    })"
                >
                    @include('livewire.chat.message-pane._composer-input')
                    @include('livewire.chat.message-pane._voice-recorder')
                </div>
            </div>
            @error('newMessage')
                <flux:text size="sm" class="mt-2 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror
        </form>
    </div>
</div>
