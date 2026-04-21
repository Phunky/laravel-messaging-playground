@props([
    'attachments' => [],
    'variant' => 'mine',
    'messageId' => null,
    'vm' => null,
])

{{--
    Renders each attachment by type (images, video, video_note, voice, document)
    using {@see AttachmentViewModel::group()} for ordering only.
--}}

@use('Phunky\Support\Chat\AttachmentViewModel')

<div
    {{ $attributes->class([
        'flex w-full min-w-0 max-w-full flex-col gap-2',
        'items-end' => $variant === 'mine',
        'items-start' => $variant !== 'mine',
    ]) }}
>
    @foreach (AttachmentViewModel::group($attachments) as $group)
        @if ($group['kind'] === AttachmentViewModel::GROUP_IMAGES)
            <x-message.type.images
                :items="$group['items']"
                :variant="$variant"
                :message-id="$messageId"
            />
        @elseif ($group['kind'] === AttachmentViewModel::GROUP_VIDEO)
            @foreach ($group['items'] as $item)
                @if ($item->hasUrl())
                    @if ($item->isVideoNote())
                        <x-message.type.video-note
                            :attachment="$item"
                            :variant="$variant"
                            :message-id="$messageId"
                        />
                    @else
                        <x-message.type.video
                            :attachment="$item"
                            :variant="$variant"
                            :message-id="$messageId"
                        />
                    @endif
                @endif
            @endforeach
        @elseif ($group['kind'] === AttachmentViewModel::GROUP_VOICE)
            <x-message.type.voice :items="$group['items']" :variant="$variant" />
        @else
            @foreach ($group['items'] as $item)
                <x-message.type.document :attachment="$item" :variant="$variant" />
            @endforeach
        @endif
    @endforeach
</div>
