@props([
    /** on_mine_bubble: inside padded bubble; on_video_tray_outside: above video note circle (flow layout, not over the clip) */
    'tone' => 'on_mine_bubble',
])

{{--
    Edit/delete for messages you sent. wire:click targets the enclosing livewire:chat.message-card.
    Optional slot `actions` for extra menu items above the defaults.
--}}

<div
    @class([
        'z-10 opacity-0 transition-opacity group-hover:opacity-100',
        'absolute end-1 top-1' => $tone === 'on_mine_bubble',
        'relative' => $tone === 'on_video_tray_outside',
    ])
>
    <flux:dropdown position="bottom" align="end">
        <flux:button
            type="button"
            variant="ghost"
            size="xs"
            icon="ellipsis-horizontal"
            @class([
                '!size-7',
                '!text-emerald-50 hover:!bg-emerald-700/50 dark:!text-emerald-50 dark:hover:!bg-emerald-600/50' => $tone === 'on_mine_bubble',
                '!text-zinc-600 hover:!bg-zinc-200/90 dark:!text-zinc-300 dark:hover:!bg-zinc-700/70' => $tone === 'on_video_tray_outside',
            ])
        />

        <flux:popover class="min-w-36 p-1">
            @isset($prepend)
                {{ $prepend }}
            @endisset
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
