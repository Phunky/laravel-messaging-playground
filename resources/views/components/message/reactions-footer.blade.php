@props([
    'messageId',
    'conversationId',
    'messageAlignment',
    'showEdgePicker' => true,
])

<x-message.reaction-summary-island
    :message-id="$messageId"
    :conversation-id="$conversationId"
    :message-alignment="$messageAlignment"
/>
@if ($showEdgePicker)
    <x-message.reaction-picker-island
        :message-id="$messageId"
        :conversation-id="$conversationId"
        :message-alignment="$messageAlignment"
        :inline="false"
    />
@endif
