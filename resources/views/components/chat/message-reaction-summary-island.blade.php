@props([
    'messageId',
    'conversationId',
    'messageAlignment',
])

{{-- Thin wrapper: Livewire island lives in resources/views/livewire/chat/message-reactions-summary.blade.php --}}

<livewire:chat.message-reactions-summary
    :message-id="$messageId"
    :conversation-id="$conversationId"
    :message-alignment="$messageAlignment"
    defer
/>
