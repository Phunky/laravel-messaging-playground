@props([
    'users' => [],
    'variant' => 'typing',
    /** @var 'inbox'|'pane' */
    'scope' => 'pane',
    /** @var 'indicator'|'card' */
    'layout' => 'indicator',
])

@if ($variant === 'recording')
    <flux:icon
        name="microphone"
        variant="micro"
        @class([
            'shrink-0 chat-recording-mic',
            'size-4 text-red-500' => $layout === 'card',
            'size-3.5 text-red-500' => $layout === 'indicator' && $scope !== 'inbox',
            'size-3.5' => $layout === 'indicator' && $scope === 'inbox',
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
