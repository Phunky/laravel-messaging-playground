@props([
    'items' => [],
    'index' => 0,
])

@use('Phunky\Support\Chat\ConversationMediaViewerItem')

{{--
    Presentational modal fed by the message-pane SFC (`mediaViewerItems`,
    `mediaViewerIndex`, plus `openMediaViewer` / `closeMediaViewer` actions).
--}}

@if (($items[$index] ?? null) !== null)
    <div
        class="fixed inset-0 z-[100] flex flex-col bg-zinc-950"
        wire:click="closeMediaViewer"
        wire:key="conversation-media-viewer-root"
    >
        <div
            class="relative flex min-h-0 flex-1 flex-col outline-none"
            wire:click.stop
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('Conversation media') }}"
            x-data
            x-init="$nextTick(() => { $el.focus() })"
            @keydown.escape="$wire.closeMediaViewer()"
            @keydown.arrow-left.prevent="$wire.mediaViewerGo(-1)"
            @keydown.arrow-right.prevent="$wire.mediaViewerGo(1)"
        >
            <div class="absolute end-3 top-3 z-20 sm:end-4 sm:top-4">
                <flux:button
                    type="button"
                    wire:click="closeMediaViewer"
                    variant="subtle"
                    size="sm"
                    icon="x-mark"
                    class="!rounded-full !bg-black/50 !text-white hover:!bg-black/70 dark:!bg-white/10 dark:hover:!bg-white/20"
                    title="{{ __('Close') }}"
                />
            </div>

            <div class="relative flex min-h-0 flex-1 items-center justify-center px-4 pb-4 pt-14 sm:px-16">
                @if (count($items) > 1)
                    <div class="absolute start-2 top-1/2 z-10 -translate-y-1/2 sm:start-4">
                        <button
                            type="button"
                            wire:click.stop="mediaViewerGo(-1)"
                            class="flex size-11 items-center justify-center rounded-full bg-black/50 text-white shadow-sm backdrop-blur-sm transition hover:bg-black/70 dark:bg-white/10 dark:hover:bg-white/20"
                            aria-label="{{ __('Previous') }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-6" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                        </button>
                    </div>
                    <div class="absolute end-2 top-1/2 z-10 -translate-y-1/2 sm:end-4">
                        <button
                            type="button"
                            wire:click.stop="mediaViewerGo(1)"
                            class="flex size-11 items-center justify-center rounded-full bg-black/50 text-white shadow-sm backdrop-blur-sm transition hover:bg-black/70 dark:bg-white/10 dark:hover:bg-white/20"
                            aria-label="{{ __('Next') }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-6" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </div>
                @endif

                <div class="flex max-h-full min-h-0 w-full max-w-5xl items-center justify-center">
                    @if (($items[$index]['type'] ?? '') === 'video')
                        <video
                            wire:key="media-viewer-main-{{ $items[$index]['id'] }}"
                            src="{{ $items[$index]['url'] }}"
                            crossorigin="anonymous"
                            controls
                            playsinline
                            preload="{{ ConversationMediaViewerItem::videoPosterPreload($items[$index]) }}"
                            data-mime-type="{{ ConversationMediaViewerItem::videoPosterDataMimeType($items[$index]) }}"
                            class="chat-video-poster max-h-[min(85vh,calc(100vh-11rem))] w-full max-w-full object-contain"
                        ></video>
                    @else
                        <img
                            wire:key="media-viewer-main-{{ $items[$index]['id'] }}"
                            src="{{ $items[$index]['url'] }}"
                            alt="{{ $items[$index]['filename'] }}"
                            class="max-h-[min(85vh,calc(100vh-11rem))] w-full max-w-full object-contain"
                        />
                    @endif
                </div>
            </div>

            <div
                class="flex h-28 shrink-0 gap-2 overflow-x-auto border-t border-white/10 px-3 py-3 sm:px-4"
                wire:click.stop
            >
                @foreach ($items as $i => $item)
                    <button
                        type="button"
                        wire:click="mediaViewerSetIndex({{ $i }})"
                        @class([
                            'relative h-20 w-20 shrink-0 overflow-hidden rounded-md ring-2 transition',
                            'ring-emerald-500 ring-offset-2 ring-offset-zinc-950' => $i === $index,
                            'ring-1 ring-white/20 hover:ring-white/40' => $i !== $index,
                        ])
                        @if ($i === $index) aria-current="true" @endif
                        aria-label="{{ __('Go to item :current of :total', ['current' => $i + 1, 'total' => count($items)]) }}"
                    >
                        @if (($item['type'] ?? '') === 'video')
                            <video
                                src="{{ $item['url'] }}"
                                crossorigin="anonymous"
                                muted
                                playsinline
                                preload="{{ ConversationMediaViewerItem::videoPosterPreload($item) }}"
                                data-mime-type="{{ ConversationMediaViewerItem::videoPosterDataMimeType($item) }}"
                                class="chat-video-poster h-full w-full object-cover"
                            ></video>
                        @else
                            <img
                                src="{{ $item['url'] }}"
                                alt=""
                                class="h-full w-full object-cover"
                            />
                        @endif
                        @if (($item['mime_type'] ?? '') === 'image/gif')
                            <span
                                class="absolute bottom-1 start-1 rounded bg-white px-1 text-[0.65rem] font-semibold leading-none text-black"
                            >
                                GIF
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    </div>
@endif
