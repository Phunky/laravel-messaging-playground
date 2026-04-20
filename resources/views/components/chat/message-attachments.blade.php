@props([
    'attachments' => [],
    'variant' => 'mine',
    'messageId' => null,
])

{{--
    Groups attachments (images, stand-alone videos, voice notes, documents) via
    Phunky\Support\Chat\AttachmentViewModel::group so this template has no
    inline PHP. Children are fed DTOs directly.
--}}

@use('Phunky\Support\Chat\AttachmentViewModel')
@use('Illuminate\Support\Js')

<div
    {{ $attributes->class([
        'flex flex-col gap-2',
        'items-end' => $variant === 'mine',
        'items-start' => $variant !== 'mine',
    ]) }}
>
    @foreach (AttachmentViewModel::group($attachments) as $group)
        @if ($group['kind'] === AttachmentViewModel::GROUP_IMAGES)
            <x-chat.message-image-grid :items="$group['items']" :variant="$variant" :message-id="$messageId" />
        @elseif ($group['kind'] === AttachmentViewModel::GROUP_VIDEO)
            @foreach ($group['items'] as $item)
                @if ($item->hasUrl())
                    {{-- Width cap matches bubble's max-w min(85%,36rem); w-auto avoids letterboxing portrait videos. --}}
                    <div
                        @class([
                            'relative w-fit min-w-0 overflow-hidden rounded-lg bg-zinc-950 ring-1',
                            'ring-emerald-950/15 dark:ring-white/15' => $variant === 'mine',
                            'ring-zinc-200/80 dark:ring-zinc-600/80' => $variant !== 'mine',
                        ])
                    >
                        <video
                            src="{{ $item->url }}"
                            controls
                            playsinline
                            preload="metadata"
                            class="block h-auto max-h-[min(70vh,32rem)] w-auto max-w-[calc(0.6*min(85vw,36rem))] object-contain"
                        ></video>
                        <div class="pointer-events-none absolute end-2 top-2 z-10 sm:end-3 sm:top-3">
                            <flux:button
                                type="button"
                                x-on:click.stop="Livewire.dispatch('message-pane-open-media-viewer', {{ Js::from($item->openMediaPayload($messageId !== null ? (int) $messageId : null)) }})"
                                variant="subtle"
                                size="xs"
                                icon="arrows-pointing-out"
                                class="pointer-events-auto !rounded-full !bg-black/55 !text-white shadow-sm hover:!bg-black/75 dark:!bg-white/15 dark:hover:!bg-white/25"
                                title="{{ __('Open in media viewer') }}"
                            />
                        </div>
                    </div>
                @endif
            @endforeach
        @elseif ($group['kind'] === AttachmentViewModel::GROUP_VOICE)
            @foreach ($group['items'] as $item)
                @if ($item->hasUrl())
                    <div class="max-w-xs min-w-[220px] w-full">
                        <audio
                            src="{{ $item->url }}"
                            controls
                            preload="metadata"
                            controlslist="nodownload noplaybackrate"
                            class="chat-native-audio w-full"
                        ></audio>
                    </div>
                @endif
            @endforeach
        @else
            @foreach ($group['items'] as $item)
                <x-chat.message-document-attachment :attachment="$item" :variant="$variant" />
            @endforeach
        @endif
    @endforeach
</div>
