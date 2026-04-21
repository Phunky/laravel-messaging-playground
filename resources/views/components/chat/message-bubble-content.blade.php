@props([
    'vm',
])

@php
    $variant = $vm->isMe ? 'mine' : 'other';
@endphp

@if ($vm->hasAttachments())
    <x-chat.message-attachment-stack
        class="mb-2"
        :attachments="$vm->attachments"
        :message-id="$vm->id"
        :variant="$variant"
        :vm="$vm"
    />
@endif

<x-chat.message-text-block :vm="$vm" />
