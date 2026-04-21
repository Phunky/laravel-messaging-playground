@props([
    'attachments' => [],
    'variant' => 'mine',
    'messageId' => null,
])

{{-- Backwards-compatible alias for <x-message.attachment-stack>. --}}

<x-message.attachment-stack
    {{ $attributes }}
    :attachments="$attachments"
    :variant="$variant"
    :message-id="$messageId"
/>
