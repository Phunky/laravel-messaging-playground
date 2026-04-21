@props([
    'users' => [],
    'variant' => 'typing',
])

{{--
    Whisper / presence row: same max width and alignment as normal incoming messages
    (see livewire chat.message-card rows). Does not use message-sent-meta (no send time),
    message-context-menu, or message-reactions — whispers are ephemeral system rows, not DMs.
    Label/variant logic lives in Phunky\Support\Chat\WhisperLabel.
--}}

<div class="flex justify-start last:mb-6">
    <div class="max-w-[80%] w-fit">
        <div
            class="relative min-w-0 max-w-full rounded-lg bg-zinc-200 px-3 py-2 text-sm text-zinc-900 dark:bg-zinc-700 dark:text-zinc-50"
        >
            <div class="flex items-center gap-1.5 text-sm italic text-zinc-600 dark:text-zinc-300" aria-live="polite">
                <x-chat.whisper-leading :users="$users" :variant="$variant" layout="card" />
            </div>
        </div>
    </div>
</div>
