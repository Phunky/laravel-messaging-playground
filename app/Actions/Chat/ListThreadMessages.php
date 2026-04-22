<?php

namespace Phunky\Actions\Chat;

use Illuminate\Pagination\Cursor;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\Models\User;
use Phunky\Support\Chat\ChatMessageSerializer;

final class ListThreadMessages
{
    public function __construct(
        private ChatMessageSerializer $serializer,
    ) {}

    /**
     * @return array{messages: list<array<string, mixed>>, next_cursor: ?string, has_more: bool}
     */
    public function __invoke(User $user, int $conversationId, ?string $cursor = null, int $perPage = 50): array
    {
        if (! $user->conversations()->whereKey($conversationId)->exists()) {
            return ['messages' => [], 'next_cursor' => null, 'has_more' => false];
        }

        $conversation = Conversation::query()->find($conversationId);
        if (! $conversation instanceof Conversation) {
            return ['messages' => [], 'next_cursor' => null, 'has_more' => false];
        }

        $query = $conversation->messages()->with(['messageable', 'attachments'])->reorder()->latest('sent_at')->latest('id');

        if ($cursor === null || $cursor === '') {
            $page = $query->cursorPaginate($perPage);
        } else {
            $page = $query->cursorPaginate($perPage, ['*'], 'cursor', Cursor::fromEncoded($cursor));
        }

        $chunk = collect($page->items())
            ->map(fn (Message $m) => $this->serializer->serialize($m, $user))
            ->values()
            ->all();

        $chunk = $this->serializer->hydrateReadReceipts($chunk, $conversation, $user);
        $ordered = array_reverse($chunk);

        return [
            'messages' => $ordered,
            'next_cursor' => $page->nextCursor()?->encode(),
            'has_more' => $page->hasMorePages(),
        ];
    }
}
