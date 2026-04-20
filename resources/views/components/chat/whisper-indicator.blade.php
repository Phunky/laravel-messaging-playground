@props([
    'users' => [],
    'variant' => 'typing',
    'scope' => 'pane',
])

{{--
    Pure presentational whisper indicator. All label/color logic is encapsulated
    in Phunky\Support\Chat\WhisperLabel and @class bindings; no @php blocks.
--}}

<span
    @class([
        'flex min-w-0 items-center gap-1.5',
        'text-sm italic text-red-600 dark:text-red-400' => $variant === 'recording' && $scope === 'inbox',
        'text-xs italic text-zinc-500 dark:text-zinc-400' => $variant === 'recording' && $scope !== 'inbox',
        'text-sm italic text-emerald-600 dark:text-emerald-400' => $variant !== 'recording' && $scope === 'inbox',
        'text-xs italic text-zinc-500 dark:text-zinc-400' => $variant !== 'recording' && $scope !== 'inbox',
    ])
    aria-live="polite"
>
    @if ($variant === 'recording')
        <flux:icon
            name="microphone"
            variant="micro"
            @class([
                'size-3.5 shrink-0 chat-recording-mic',
                'text-red-500' => $scope !== 'inbox',
            ])
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
</span>
