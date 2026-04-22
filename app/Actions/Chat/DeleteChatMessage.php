<?php

namespace Phunky\Actions\Chat;

use Phunky\LaravelMessaging\Exceptions\CannotMessageException;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\Models\User;

final class DeleteChatMessage
{
    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function __invoke(User $user, int $conversationId, int $messageId, MessagingService $messaging): array
    {
        if (! $user->conversations()->whereKey($conversationId)->exists()) {
            return ['ok' => false, 'error' => __('Unauthorized.')];
        }

        $message = Message::query()->with('messageable')->find($messageId);
        if (! $message instanceof Message || (int) $message->conversation_id !== $conversationId) {
            return ['ok' => false, 'error' => __('Message not found.')];
        }

        $sender = $message->messageable;
        if (! $sender instanceof User || (string) $sender->getKey() !== (string) $user->getKey()) {
            return ['ok' => false, 'error' => __('Unauthorized.')];
        }

        try {
            $messaging->deleteMessage($message, $user);

            return ['ok' => true];
        } catch (CannotMessageException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
