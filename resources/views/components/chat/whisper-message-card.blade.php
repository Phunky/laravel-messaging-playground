@props([
    'users' => [],
    'variant' => 'typing',
])

{{--
    Pure presentational whisper "bubble" rendered at the bottom of the active
    thread. Label/variant logic lives in Phunky\Support\Chat\WhisperLabel.
--}}

<div class="flex justify-start">
    <div class="max-w-[80%] w-fit">
        <x-chat.bubble :is-me="false">
            <div class="flex items-center gap-1.5 text-sm italic text-zinc-600 dark:text-zinc-300" aria-live="polite">
                <x-chat.whisper-leading :users="$users" :variant="$variant" layout="card" />
            </div>
        </x-chat.bubble>
    </div>
</div>
