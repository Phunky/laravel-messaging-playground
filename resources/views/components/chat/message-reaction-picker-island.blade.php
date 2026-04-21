@props([
    'messageId',
    'conversationId',
    'messageAlignment',
    'inline' => false,
])

{{-- Thin wrapper: Livewire island lives in resources/views/livewire/chat/message-reactions-picker.blade.php --}}

<livewire:chat.message-reactions-picker
    :message-id="$messageId"
    :conversation-id="$conversationId"
    :message-alignment="$messageAlignment"
    :inline="$inline"
    defer
/>
