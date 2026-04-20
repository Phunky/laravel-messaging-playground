@props([
    'users' => [],
    'variant' => 'typing',
])

{{--
    Pure presentational whisper "bubble" rendered at the bottom of the active
    thread. Label/variant logic lives in Phunky\Support\Chat\WhisperLabel.
--}}

<div class="flex justify-start px-4">
    <div class="max-w-[min(85%,36rem)] w-fit">
        <flux:card size="sm" class="relative">
            <div class="flex items-center gap-1.5 text-sm italic text-zinc-500 dark:text-zinc-400" aria-live="polite">
                @if ($variant === 'recording')
                    <flux:icon
                        name="microphone"
                        variant="micro"
                        class="size-4 shrink-0 chat-recording-mic text-red-500"
                        aria-hidden="true"
                    />
                @else
                    <span class="inline-flex shrink-0 gap-0.5" aria-hidden="true">
                        <span class="chat-typing-dot block size-1 rounded-full bg-current" style="animation-delay: 0ms"></span>
                        <span class="chat-typing-dot block size-1 rounded-full bg-current" style="animation-delay: 150ms"></span>
                        <span class="chat-typing-dot block size-1 rounded-full bg-current" style="animation-delay: 300ms"></span>
                    </span>
                @endif
                <span class="truncate">{{ \Phunky\Support\Chat\WhisperLabel::for($variant, \Phunky\Support\Chat\WhisperUser::namesFrom($users)) }}</span>
            </div>
        </flux:card>
    </div>
</div>
