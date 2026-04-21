@props([
    'vm',
    'showReadTicks' => null,
    'includeEdited' => true,
])

@php
    /** @var \Phunky\Support\Chat\MessageViewModel $vm */

    $showTicks = $showReadTicks ?? $vm->isMe;
    $readReceipt = $vm->readReceiptDisplay;
@endphp

@if ($vm->sentAt !== null && $vm->sentAt !== '')
    <div
        {{ $attributes->class([
            'flex min-w-0 shrink-0 flex-nowrap items-center gap-x-1 rounded-full px-2 py-0.5 text-[0.65rem] font-medium tabular-nums shadow-sm ring-1 backdrop-blur-[2px]',
            'bg-black/55 text-white ring-white/15' => $vm->isMe,
            'bg-white/90 text-zinc-800 ring-zinc-300/80 dark:bg-zinc-900/75 dark:text-zinc-100 dark:ring-zinc-600/50' => ! $vm->isMe,
        ]) }}
    >
        <x-message.timestamp :vm="$vm" :include-edited="$includeEdited" />
        @if ($showTicks && $vm->isMe)
            @if ($readReceipt === 'read')
                <span class="text-[0.6rem] leading-none tracking-tight text-sky-300" aria-hidden="true" title="{{ __('Read') }}">✓✓</span>
            @else
                <span class="text-[0.6rem] leading-none tracking-tight text-zinc-300" aria-hidden="true" title="{{ __('Sent') }}">✓✓</span>
            @endif
        @endif
    </div>
@endif
