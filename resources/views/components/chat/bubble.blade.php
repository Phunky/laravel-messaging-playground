@props(['isMe' => false])

<div
    @class([
        'relative rounded-lg px-3 py-2 text-sm',
        'bg-emerald-600 text-white' => $isMe,
        'bg-zinc-200 text-zinc-900 dark:bg-zinc-700 dark:text-zinc-50' => ! $isMe,
    ])
>
    {{ $slot }}
</div>
