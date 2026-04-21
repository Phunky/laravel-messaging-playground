{{--
    Expects $vm (Phunky\Support\Chat\MessageViewModel) and access to the
    surrounding message-card SFC's $conversationId and $this->* helpers.

    Card type (see MessageViewModel::cardType() / MessageCardType):
    - StandardBubble: one padded container (rounded-lg, etc.) for body + attachments + meta.
    - VideoNoteTray: circle + reactions + optional caption in the same container; timestamp/context outside the ring.
--}}

@php
    use Phunky\Support\Chat\MessageCardType;

    $variant = $vm->isMe ? 'mine' : 'other';
    $alignment = $vm->isMe ? 'mine' : 'others';
@endphp

<div
    @class([
        'max-w-[80%] min-w-0 w-fit',
        'min-w-[min(260px,100%)]' => $vm->usesStandardBubbleVoiceWidthFloor(),
        'group relative touch-manipulation' => $conversationId !== null,
    ])
    @if ($conversationId !== null)
        x-data="messageLongPress({{ $vm->id }})"
        @touchstart.passive="start($event)"
        @touchend="clear()"
        @touchcancel="clear()"
        @touchmove="clear()"
    @endif
>
    @if ($vm->cardType() === MessageCardType::VideoNoteTray)
        <div @class([
            'flex w-fit max-w-[80%] flex-col gap-1',
            'items-end' => $vm->isMe,
            'items-start' => ! $vm->isMe,
            'group' => $conversationId === null,
        ])>
            @if ($vm->isMe)
                <div class="flex w-full min-w-0 justify-end">
                    <x-chat.message-context-menu tone="on_video_tray_outside" />
                </div>
            @elseif ($isGroup)
                <x-chat.message-sender-label :name="$vm->senderName" />
            @endif

            @if ($conversationId !== null)
                <div
                    @class([
                        'flex items-center gap-2',
                        'flex-row-reverse' => ! $vm->isMe,
                        'mb-2' => $vm->hasBody(),
                    ])
                >
                    <x-chat.message-reaction-picker-island
                        :message-id="$vm->id"
                        :conversation-id="$conversationId"
                        :message-alignment="$alignment"
                        :inline="true"
                    />

                    <x-chat.message-attachment-stack
                        class="shrink-0"
                        :attachments="$vm->attachments"
                        :message-id="$vm->id"
                        :variant="$variant"
                        :vm="$vm"
                    />
                </div>
            @else
                <x-chat.message-attachment-stack
                    @class([
                        'mb-2' => $vm->hasBody(),
                    ])
                    :attachments="$vm->attachments"
                    :message-id="$vm->id"
                    :variant="$variant"
                    :vm="$vm"
                />
            @endif

            @if ($vm->showVideoNoteInlineMeta())
                <div class="flex w-full min-w-0 justify-end">
                    <x-chat.message-sent-meta :vm="$vm" :include-edited="false" />
                </div>
            @endif

            @if ($vm->hasBody())
                <div
                    @class([
                        'relative mt-1 min-w-0 max-w-full rounded-lg px-3 py-2 text-sm',
                        'bg-emerald-600 text-white' => $vm->isMe,
                        'bg-zinc-200 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-50' => ! $vm->isMe,
                    ])
                >
                    <x-chat.message-text-block :vm="$vm" />
                </div>
            @endif
        </div>
    @else
        <div
            @class([
                'relative min-w-0 max-w-full rounded-lg px-3 py-2 text-sm',
                'bg-emerald-600 text-white' => $vm->isMe,
                'bg-zinc-200 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-50' => ! $vm->isMe,
            ])
        >
            @if ($vm->isMe)
                <x-chat.message-context-menu tone="on_mine_bubble" />
            @elseif ($isGroup)
                <x-chat.message-sender-label :name="$vm->senderName" />
            @endif

            <x-chat.message-bubble-content :vm="$vm" />
        </div>
    @endif

    @if ($conversationId !== null)
        <x-chat.message-reactions-footer
            :message-id="$vm->id"
            :conversation-id="$conversationId"
            :message-alignment="$alignment"
            :show-edge-picker="$vm->cardType() !== MessageCardType::VideoNoteTray"
        />
    @endif
</div>
