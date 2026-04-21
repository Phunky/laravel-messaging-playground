@props([
    'variant' => 'mine',
    /** @var 'default'|'compact' Preset width: default matches sent bubble + recorder; compact fits composer chip row. */
    'size' => 'default',
])

<div
    {{ $attributes->class([
        'relative aspect-square shrink-0 overflow-hidden rounded-full bg-zinc-950',
        'w-[min(72vw,17.5rem)] ring-2' => $size === 'default',
        'h-16 w-16 max-w-none ring-1' => $size === 'compact',
        'ring-emerald-300/90' => $variant === 'mine',
        'ring-zinc-300 dark:ring-zinc-600' => $variant !== 'mine',
    ]) }}
>
    {{ $slot }}
</div>
