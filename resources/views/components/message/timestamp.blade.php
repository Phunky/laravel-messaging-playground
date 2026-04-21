@props([
    'vm' => null,
    /** ISO8601 string; used with {@see $preset} when no MessageViewModel */
    'iso' => null,
    /**
     * bubble: time in a message (default). inbox: conversation list rail.
     * date_separator: sticky day label in the thread.
     */
    'preset' => 'bubble',
    'includeEdited' => true,
])

@php
    use Phunky\Support\Chat\ChatTimestamp;
    use Phunky\Support\Chat\MessageViewModel;
@endphp

@if ($vm instanceof MessageViewModel && $vm->sentAt !== null && $vm->sentAt !== '')
    <span {{ $attributes->class('whitespace-nowrap') }}>
        {{ $vm->formattedSentAt() }}
        @if ($includeEdited && $vm->isEdited())
            <span class="opacity-90"> · {{ __('Edited') }} {{ $vm->formattedEditedAt() }}</span>
        @endif
    </span>
@elseif ($iso !== null && $iso !== '')
    <span {{ $attributes }}>{{ match ($preset) {
        'inbox' => ChatTimestamp::inbox($iso),
        'date_separator' => ChatTimestamp::dateSeparator($iso),
        default => ChatTimestamp::bubbleTime($iso),
    } }}</span>
@endif
