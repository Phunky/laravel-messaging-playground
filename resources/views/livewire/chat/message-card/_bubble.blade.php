{{--
    Expects $vm (Phunky\Support\Chat\MessageViewModel) and access to the
    surrounding message-card SFC's $conversationId and $this->* helpers.
    Renders the actual bubble (card + optional dropdown + attachments + body +
    timestamp) plus the deferred message-reactions island beneath it.
--}}

<div
    @class([
        'max-w-[80%] w-fit',
        'group relative touch-manipulation' => $conversationId !== null,
    ])
    @if ($conversationId !== null)
        x-data="{
            _t: null,
            start(ev) {
                if (! ev.touches || ev.touches.length === 0) {
                    return;
                }
                this.clear();
                const id = {{ $vm->id }};
                this._t = setTimeout(() => {
                    window.Livewire?.dispatch('open-message-reaction-picker', { messageId: id });
                    this._t = null;
                }, 480);
            },
            clear() {
                if (this._t) {
                    clearTimeout(this._t);
                    this._t = null;
                }
            }
        }"
        @touchstart.passive="start($event)"
        @touchend="clear()"
        @touchcancel="clear()"
        @touchmove="clear()"
    @endif
>
    <flux:card
        size="sm"
        @class([
            'relative !border-0 !rounded-lg !px-3 !py-2 !shadow-none',
            '!bg-emerald-600 !text-white dark:!bg-emerald-600 dark:!text-white' => $vm->isMe,
            '!bg-zinc-200 !text-zinc-900 dark:!bg-zinc-700 dark:!text-zinc-50' => ! $vm->isMe,
        ])
    >
        @if ($vm->isMe)
            <div class="absolute end-1 top-1 z-10 opacity-0 transition-opacity group-hover:opacity-100">
                <flux:dropdown position="bottom" align="end">
                    <flux:button
                        type="button"
                        variant="ghost"
                        size="xs"
                        icon="ellipsis-horizontal"
                        class="!size-7 !text-emerald-50 hover:!bg-emerald-700/50 dark:!text-emerald-50 dark:hover:!bg-emerald-600/50"
                    />

                    <flux:popover class="min-w-36 p-1">
                        <button
                            type="button"
                            wire:click="startEdit"
                            class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
                        >
                            {{ __('Edit') }}
                        </button>
                        <button
                            type="button"
                            wire:click="requestDelete"
                            class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                        >
                            {{ __('Delete') }}
                        </button>
                    </flux:popover>
                </flux:dropdown>
            </div>
        @elseif ($isGroup)
            <flux:subheading class="mb-1">{{ $vm->senderName }}</flux:subheading>
        @endif

        @if ($vm->hasAttachments())
            <x-chat.message-attachments
                class="mb-2"
                :attachments="$vm->attachments"
                :message-id="$vm->id"
                :variant="$vm->isMe ? 'mine' : 'other'"
            />
        @endif

        @if ($vm->hasBody())
            <div class="min-w-0 [display:flow-root]">
                <span class="whitespace-pre-wrap">{{ $vm->body }}</span>

                @if ($vm->sentAt !== null && $vm->sentAt !== '')
                    <span
                        @class([
                            'float-right ms-2 mt-1 -mb-0.5 shrink-0 whitespace-nowrap text-xs opacity-70 select-none',
                            '!text-emerald-50/90' => $vm->isMe,
                        ])
                    >
                        {{ $vm->formattedSentAt() }}@if ($vm->isEdited())<span class="opacity-90"> · {{ __('Edited') }} {{ $vm->formattedEditedAt() }}</span>@endif
                    </span>
                @endif
            </div>
        @elseif ($vm->sentAt !== null && $vm->sentAt !== '')
            <div class="mt-1 -mb-0.5 flex justify-end">
                <span
                    @class([
                        'whitespace-nowrap text-xs opacity-70 select-none',
                        '!text-emerald-50/90' => $vm->isMe,
                    ])
                >
                    {{ $vm->formattedSentAt() }}@if ($vm->isEdited())<span class="opacity-90"> · {{ __('Edited') }} {{ $vm->formattedEditedAt() }}</span>@endif
                </span>
            </div>
        @endif
    </flux:card>

    @if ($conversationId !== null)
        <livewire:chat.message-reactions
            :message-id="$vm->id"
            :conversation-id="$conversationId"
            :message-alignment="$vm->isMe ? 'mine' : 'others'"
            defer
        />
    @endif
</div>
