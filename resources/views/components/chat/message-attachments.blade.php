@props([
    'attachments' => [],
    'variant' => 'mine',
    'messageId' => null,
])

@php
    /** @var list<array{id: int, type: string, url: string, filename: string, mime_type: ?string, size: ?int}> $attachments */
    $mediaTypes = config('messaging.media_attachment_types', []);
    $groups = [];
    $imageRun = [];
    $flushImageRun = function () use (&$groups, &$imageRun): void {
        if ($imageRun !== []) {
            $groups[] = ['kind' => 'images', 'items' => $imageRun];
            $imageRun = [];
        }
    };
    foreach ($attachments as $item) {
        $mediaType = $item['type'] ?? '';
        $isKind = array_key_exists($mediaType, $mediaTypes);
        $mime = (string) ($item['mime_type'] ?? '');
        $hasUrl = ! empty($item['url']);
        $isLegacyVideoAsImage = $hasUrl && $mediaType === 'image' && str_starts_with($mime, 'video/');
        $isImageSlot = $hasUrl && $mediaType === 'image'
            && ($mime === '' || str_starts_with($mime, 'image/'))
            && ! $isLegacyVideoAsImage;
        $isVideoSlot = $hasUrl && (
            ($mediaType === 'video' && ($mime === '' || str_starts_with($mime, 'video/')))
            || $isLegacyVideoAsImage
        );
        if ($isImageSlot) {
            $imageRun[] = $item;

            continue;
        }
        if ($isVideoSlot) {
            $flushImageRun();
            $groups[] = ['kind' => 'video', 'items' => [$item]];

            continue;
        }
        $flushImageRun();
        $isVoice = $mediaType === 'voice_note' && $isKind && ! empty($item['url']);
        $isDocument = $mediaType === 'document' && $isKind && ! empty($item['url']);
        if ($isVoice) {
            $groups[] = ['kind' => 'voice', 'items' => [$item]];

            continue;
        }
        if ($isDocument) {
            $groups[] = ['kind' => 'document', 'items' => [$item]];
        }
    }
    $flushImageRun();

    $isMine = $variant === 'mine';
@endphp

<div
    {{ $attributes->class([
        'flex flex-col gap-2',
        'items-end' => $isMine,
        'items-start' => ! $isMine,
    ]) }}
>
    @foreach ($groups as $group)
        @if ($group['kind'] === 'images')
            <x-chat.message-image-grid :items="$group['items']" :variant="$variant" :message-id="$messageId" />
        @elseif ($group['kind'] === 'video')
            @foreach ($group['items'] as $item)
                @if (! empty($item['url']))
                    @php
                        $__openMediaPayload = ['attachmentId' => (int) $item['id']];
                        if ($messageId !== null) {
                            $__openMediaPayload['messageId'] = (int) $messageId;
                        }
                    @endphp
                    {{-- Same width cap as ~60% of message column max (matches bubble max-w min(85%,36rem)). w-auto avoids letterboxing portrait video in a wide box. --}}
                    <div
                        @class([
                            'relative w-fit min-w-0 overflow-hidden rounded-lg bg-zinc-950 ring-1',
                            'ring-emerald-950/15 dark:ring-white/15' => $isMine,
                            'ring-zinc-200/80 dark:ring-zinc-600/80' => ! $isMine,
                        ])
                    >
                        <video
                            src="{{ $item['url'] }}"
                            controls
                            playsinline
                            preload="metadata"
                            class="block h-auto max-h-[min(70vh,32rem)] w-auto max-w-[calc(0.6*min(85vw,36rem))] object-contain"
                        ></video>
                        <div class="pointer-events-none absolute end-2 top-2 z-10 sm:end-3 sm:top-3">
                            <flux:button
                                type="button"
                                x-on:click.stop="Livewire.dispatch('message-pane-open-media-viewer', {{ \Illuminate\Support\Js::from($__openMediaPayload) }})"
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
        @elseif ($group['kind'] === 'voice')
            @foreach ($group['items'] as $item)
                @if (! empty($item['url']))
                    <div @class(['max-w-xs', 'min-w-[220px]', 'w-full'])>
                        <audio
                            src="{{ $item['url'] }}"
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
                <x-chat.message-document-attachment
                    :url="$item['url']"
                    :filename="$item['filename'] ?? ''"
                    :mime-type="$item['mime_type'] ?? null"
                    :size="$item['size'] ?? null"
                    :variant="$variant"
                />
            @endforeach
        @endif
    @endforeach
</div>
