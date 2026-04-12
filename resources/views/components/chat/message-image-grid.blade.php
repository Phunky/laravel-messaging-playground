@props([
    'items' => [],
    'variant' => 'mine',
    'messageId' => null,
])

@php
    /** @var list<array{id: int, type: string, url: string, filename: string, mime_type: ?string, size: ?int}> $items */
    $filled = array_values(array_filter($items, fn (array $item): bool => ! empty($item['url'])));
    $total = count($filled);
    $overflow = max(0, $total - 4);
    $isMine = $variant === 'mine';
@endphp

@if ($total > 0)
    @if ($overflow > 0)
        @php $chunk = array_slice($filled, 0, 4); @endphp
        <div
            @class([
                'grid w-full max-w-60 grid-cols-2 grid-rows-2 gap-1 aspect-square',
            ])
        >
            @foreach ($chunk as $index => $item)
                @php
                    $isLastCell = $index === 3;
                    $__openMediaPayload = ['attachmentId' => (int) $item['id']];
                    if ($messageId !== null) {
                        $__openMediaPayload['messageId'] = (int) $messageId;
                    }
                @endphp
                <div
                    @class([
                        'col-span-1 row-span-1',
                        'min-h-0 min-w-0 overflow-hidden rounded-lg ring-1',
                        'ring-emerald-950/15 dark:ring-white/15' => $isMine,
                        'bg-zinc-100 ring-zinc-200/80 dark:bg-zinc-800/80 dark:ring-zinc-600/80' => ! $isMine,
                    ])
                >
                    <button
                        type="button"
                        wire:key="open-media-{{ (int) $item['id'] }}-{{ $isLastCell ? 'more' : 'single' }}"
                        x-on:click.prevent="Livewire.dispatch('message-pane-open-media-viewer', {{ \Illuminate\Support\Js::from($__openMediaPayload) }})"
                        class="relative block h-full w-full min-h-0 text-start focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
                        title="{{ __('Open in media viewer') }}"
                        aria-label="{{ __('Open in media viewer') }}"
                    >
                        <img
                            src="{{ $item['url'] }}"
                            alt="{{ $item['filename'] ?? '' }}"
                            loading="eager"
                            decoding="async"
                            class="pointer-events-none h-full w-full object-cover object-center"
                        />
                        @if ($isLastCell)
                            <div
                                class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-[inherit] bg-black/50"
                                aria-hidden="true"
                            >
                                <span class="text-2xl font-semibold tabular-nums text-white">
                                    +{{ $overflow }}
                                </span>
                            </div>
                        @endif
                    </button>
                </div>
            @endforeach
        </div>
    @else
        @php $chunk = $filled; $n = count($chunk); @endphp
        <div
            @class([
                'grid w-full max-w-60 grid-cols-2 grid-rows-2 gap-1 aspect-square',
            ])
        >
            @foreach ($chunk as $index => $item)
                @php
                    $span = match (true) {
                        $n === 1 => 'col-span-2 row-span-2',
                        $n === 2 => 'col-span-1 row-span-2',
                        $n === 3 && $index === 2 => 'col-span-2 row-span-1',
                        $n === 3 => 'col-span-1 row-span-1',
                        default => 'col-span-1 row-span-1',
                    };
                    $__openMediaPayload = ['attachmentId' => (int) $item['id']];
                    if ($messageId !== null) {
                        $__openMediaPayload['messageId'] = (int) $messageId;
                    }
                @endphp
                <div
                    @class([
                        $span,
                        'min-h-0 min-w-0 overflow-hidden rounded-lg ring-1',
                        'ring-emerald-950/15 dark:ring-white/15' => $isMine,
                        'bg-zinc-100 ring-zinc-200/80 dark:bg-zinc-800/80 dark:ring-zinc-600/80' => ! $isMine,
                    ])
                >
                    <button
                        type="button"
                        wire:key="open-media-{{ (int) $item['id'] }}"
                        x-on:click.prevent="Livewire.dispatch('message-pane-open-media-viewer', {{ \Illuminate\Support\Js::from($__openMediaPayload) }})"
                        class="block h-full w-full min-h-0 text-start focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
                        title="{{ __('Open in media viewer') }}"
                        aria-label="{{ __('Open in media viewer') }}"
                    >
                        <img
                            src="{{ $item['url'] }}"
                            alt="{{ $item['filename'] ?? '' }}"
                            loading="eager"
                            decoding="async"
                            class="pointer-events-none h-full w-full object-cover object-center"
                        />
                    </button>
                </div>
            @endforeach
        </div>
    @endif
@endif
