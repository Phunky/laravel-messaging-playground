@props([
    'items' => [],
    'variant' => 'mine',
])

{{--
    Pill player; timestamp via message-text-block. min(220px,100%) keeps a comfortable width without
    overflowing narrow bubbles (see message-card / group layout).
--}}

@foreach ($items as $item)
    @if ($item->hasUrl())
        <div
            @class([
                'relative w-full min-w-[min(220px,100%)] max-w-full overflow-hidden',
                'rounded-full shadow-sm ring-1',
                'bg-emerald-600/95 ring-emerald-400/40' => $variant === 'mine',
                'bg-zinc-200 ring-zinc-300 dark:bg-zinc-700 dark:ring-zinc-600' => $variant !== 'mine',
            ])
        >
            <audio
                src="{{ $item->url }}"
                controls
                preload="metadata"
                controlslist="nodownload noplaybackrate"
                @class([
                    'chat-native-audio h-10 w-full min-w-0 max-w-full',
                    'accent-emerald-200' => $variant === 'mine',
                ])
            ></audio>
        </div>
    @endif
@endforeach
