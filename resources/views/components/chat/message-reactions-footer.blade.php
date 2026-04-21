@props([
    'messageId',
    'conversationId',
    'messageAlignment',
    'showEdgePicker' => true,
])

<x-chat.message-reaction-summary-island
    :message-id="$messageId"
    :conversation-id="$conversationId"
    :message-alignment="$messageAlignment"
/>
@if ($showEdgePicker)
    <x-chat.message-reaction-picker-island
        :message-id="$messageId"
        :conversation-id="$conversationId"
        :message-alignment="$messageAlignment"
        :inline="false"
    />
@endif
