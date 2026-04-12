@props([
    'msg' => [],
    'conversationId' => null,
    'isGroup' => false,
])

@php
    /** @var array{id: int, body: string, sent_at: ?string, edited_at: ?string, sender_id: string, sender_name: string, is_me: bool, attachments?: list<array{id: int, type: string, url: string, filename: string, mime_type: ?string, size: ?int}>} $msg */
    $attachments = $msg['attachments'] ?? [];
    $isMine = (bool) ($msg['is_me'] ?? false);
@endphp

<div
    wire:key="msg-{{ $msg['id'] }}"
    @class([
        'flex',
        'justify-end' => $isMine,
        'justify-start' => ! $isMine,
    ])
>
    @if ($isMine)
        <div
            @class([
                'max-w-[min(85%,36rem)] w-fit',
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
                        const id = {{ (int) $msg['id'] }};
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
                class="relative !border-0 !bg-emerald-600 pb-5 !text-white dark:!bg-emerald-500 dark:!text-white"
            >
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
                                x-on:click="Livewire.dispatch('message-pane-start-edit', { messageId: {{ (int) $msg['id'] }} })"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800"
                            >
                                {{ __('Edit') }}
                            </button>
                            <button
                                type="button"
                                x-on:click="Livewire.dispatch('message-pane-request-delete', { messageId: {{ (int) $msg['id'] }} })"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                            >
                                {{ __('Delete') }}
                            </button>
                        </flux:popover>
                    </flux:dropdown>
                </div>
                @if ($attachments !== [])
                    <x-chat.message-attachments
                        class="mb-2"
                        :attachments="$attachments"
                        :message-id="(int) $msg['id']"
                        variant="mine"
                    />
                @endif
                @if (($msg['body'] ?? '') !== '')
                    <flux:text class="whitespace-pre-wrap">{{ $msg['body'] }}</flux:text>
                @endif
                @if (! empty($msg['sent_at']))
                    <flux:text
                        size="sm"
                        class="mt-2 opacity-70 !text-emerald-50/90"
                    >
                        {{ \Illuminate\Support\Carbon::parse($msg['sent_at'])->timezone(config('app.timezone'))->format('g:i a') }}
                        @if (! empty($msg['edited_at']))
                            <span class="opacity-90">
                                · {{ __('Edited') }}
                                {{ \Illuminate\Support\Carbon::parse($msg['edited_at'])->timezone(config('app.timezone'))->format('H:i') }}
                            </span>
                        @endif
                    </flux:text>
                @endif
            </flux:card>
            @if ($conversationId !== null)
                <livewire:chat.message-reactions
                    :message-id="$msg['id']"
                    :conversation-id="$conversationId"
                    message-alignment="mine"
                    defer
                />
            @endif
        </div>
    @else
        <div class="flex max-w-[min(85%,36rem)] flex-row gap-2">
            @if ($isGroup)
                <flux:avatar
                    :name="$msg['sender_name']"
                    color="auto"
                    :color:seed="$msg['sender_id']"
                    size="xs"
                />
            @endif

            <div class="min-w-0 flex-1">
                <div
                    @class([
                        'max-w-[min(85%,36rem)] w-fit',
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
                                const id = {{ (int) $msg['id'] }};
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
                    <flux:card size="sm" class="relative pb-5">
                        <flux:subheading class="mb-1">{{ $msg['sender_name'] }}</flux:subheading>
                        @if ($attachments !== [])
                            <x-chat.message-attachments
                                class="mb-2"
                                :attachments="$attachments"
                                :message-id="(int) $msg['id']"
                                variant="other"
                            />
                        @endif
                        @if (($msg['body'] ?? '') !== '')
                            <flux:text class="whitespace-pre-wrap">{{ $msg['body'] }}</flux:text>
                        @endif
                        @if (! empty($msg['sent_at']))
                            <flux:text
                                size="sm"
                                class="mt-2 opacity-70"
                            >
                                {{ \Illuminate\Support\Carbon::parse($msg['sent_at'])->timezone(config('app.timezone'))->format('g:i a') }}
                                @if (! empty($msg['edited_at']))
                                    <span class="opacity-90">
                                        · {{ __('Edited') }}
                                        {{ \Illuminate\Support\Carbon::parse($msg['edited_at'])->timezone(config('app.timezone'))->format('H:i') }}
                                    </span>
                                @endif
                            </flux:text>
                        @endif
                    </flux:card>
                    @if ($conversationId !== null)
                        <livewire:chat.message-reactions
                            :message-id="$msg['id']"
                            :conversation-id="$conversationId"
                            message-alignment="others"
                            defer
                        />
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
