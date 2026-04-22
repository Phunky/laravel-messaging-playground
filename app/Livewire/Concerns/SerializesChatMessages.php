<?php

namespace Phunky\Livewire\Concerns;

use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\Models\User;
use Phunky\Support\Chat\ChatMessageSerializer;
use Phunky\Support\Chat\MessageViewModel;

trait SerializesChatMessages
{
    /**
     * Produce the JSON-serialisable message payload that the Livewire wire
     * protocol and broadcast events exchange. Kept as an array so existing
     * tests and JS listeners continue to see the same shape. Use
     * {@see self::messageViewModel()} when you want the typed DTO.
     *
     * @return array{id: int, body: string, sent_at: ?string, edited_at: ?string, sender_id: string, sender_name: string, is_me: bool, attachments: list<array{id: int, type: string, url: string, filename: string, mime_type: ?string, size: ?int}>}
     */
    protected function serializeMessage(Message $m): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            throw new \RuntimeException('Chat message serialization requires an authenticated user.');
        }

        return app(ChatMessageSerializer::class)->serialize($m, $user);
    }

    /**
     * Stamp outbound read receipt display for serialized message rows using messaging_events (MessageRead).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function hydrateReadReceipts(array $rows, ?Conversation $conversation): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return array_map(
                fn (array $row): array => app(ChatMessageSerializer::class)->withDefaultReadReceipt($row),
                $rows,
            );
        }

        return app(ChatMessageSerializer::class)->hydrateReadReceipts($rows, $conversation, $user);
    }

    /**
     * Wrap a single serialized row in a {@see MessageViewModel} for template
     * rendering.
     *
     * @param  array<string, mixed>  $serialized
     */
    protected function messageViewModel(array $serialized): MessageViewModel
    {
        return MessageViewModel::fromArray($serialized);
    }

    /**
     * Wrap a list of serialized rows and stamp `isFirstOfDay` flags so
     * templates can insert date separators without any `@php` tracking.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<MessageViewModel>
     */
    protected function messageViewModels(array $rows): array
    {
        return MessageViewModel::listFromArray($rows);
    }

    protected function stabilizeChatScroll(): void
    {
        $this->js('queueMicrotask(() => requestAnimationFrame(() => window.stabilizeChatScrollToBottom?.()))');
    }

    protected function revealChatBottomIfNearBottom(): void
    {
        $this->js('queueMicrotask(() => requestAnimationFrame(() => window.scrollChatToBottomIfNearBottom?.()))');
    }
}
