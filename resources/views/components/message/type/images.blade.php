@props([
    'items' => [],
    'variant' => 'mine',
    'messageId' => null,
])

{{--
    Image attachment type for the message thread: grid tiles, overflow "+N", media viewer dispatch.
    `items` are AttachmentViewModel instances or raw rows; layout uses AttachmentViewModel::imageGridCells.
--}}

@use('Phunky\Support\Chat\AttachmentViewModel')
@use('Illuminate\Support\Js')

@foreach (AttachmentViewModel::imageGridCells($items) as $cell)
    @if ($loop->first)
        <div class="grid w-full max-w-60 grid-cols-2 grid-rows-2 gap-1 aspect-square">
    @endif

    <div
        @class([
            $cell['span'],
            'min-h-0 min-w-0 overflow-hidden rounded-lg ring-1',
            'ring-emerald-950/15 dark:ring-white/15' => $variant === 'mine',
            'bg-zinc-100 ring-zinc-200/80 dark:bg-zinc-800/80 dark:ring-zinc-600/80' => $variant !== 'mine',
        ])
    >
        <button
            type="button"
            wire:key="open-media-{{ $cell['attachment']->id }}-{{ $cell['overflow'] > 0 ? 'more' : 'single' }}"
            x-on:click.prevent="Livewire.dispatch('message-pane-open-media-viewer', {{ Js::from($cell['attachment']->openMediaPayload($messageId !== null ? (int) $messageId : null)) }})"
            class="relative block h-full w-full min-h-0 text-start focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
            title="{{ __('Open in media viewer') }}"
            aria-label="{{ __('Open in media viewer') }}"
        >
            <img
                src="{{ $cell['attachment']->url }}"
                alt="{{ $cell['attachment']->filename }}"
                loading="eager"
                decoding="async"
                class="pointer-events-none h-full w-full object-cover object-center"
            />
            @if ($cell['overflow'] > 0)
                <div
                    class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-[inherit] bg-black/50"
                    aria-hidden="true"
                >
                    <span class="text-2xl font-semibold tabular-nums text-white">
                        +{{ $cell['overflow'] }}
                    </span>
                </div>
            @endif
        </button>
    </div>

    @if ($loop->last)
        </div>
    @endif
@endforeach
