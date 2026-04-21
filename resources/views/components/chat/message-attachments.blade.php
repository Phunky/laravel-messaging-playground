@props([
    'attachments' => [],
    'variant' => 'mine',
    'messageId' => null,
])

{{-- Backwards-compatible alias for <x-chat.message-attachment-stack>. --}}

<x-chat.message-attachment-stack
    {{ $attributes }}
    :attachments="$attachments"
    :variant="$variant"
    :message-id="$messageId"
/>
