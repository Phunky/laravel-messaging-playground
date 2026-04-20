@props([
    'vm',
    'float' => false,
    'isMe' => false,
])

<span
    @class([
        'whitespace-nowrap text-xs opacity-70 select-none',
        'float-right ms-2 mt-1 -mb-0.5 shrink-0' => $float,
        'text-emerald-50/90' => $isMe,
    ])
>
    {{ $vm->formattedSentAt() }}@if ($vm->isEdited())<span class="opacity-90"> · {{ __('Edited') }} {{ $vm->formattedEditedAt() }}</span>@endif
</span>
