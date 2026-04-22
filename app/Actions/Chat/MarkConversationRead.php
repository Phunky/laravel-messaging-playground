<?php

namespace Phunky\Actions\Chat;

use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\Models\User;

final class MarkConversationRead
{
    public function __construct(
        private MessagingService $messaging,
    ) {}

    public function __invoke(User $user, int $conversationId): bool
    {
        if (! $user->conversations()->whereKey($conversationId)->exists()) {
            return false;
        }

        $conversation = Conversation::query()->find($conversationId);
        if (! $conversation instanceof Conversation) {
            return false;
        }

        $this->messaging->markAllRead($conversation, $user);

        return true;
    }
}
