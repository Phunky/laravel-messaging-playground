<?php

namespace Phunky\Actions\Chat;

use Phunky\LaravelMessaging\Exceptions\CannotMessageException;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\Models\User;
use Phunky\Support\Chat\ChatMessageSerializer;

final class EditChatMessage
{
    public function __construct(
        private ChatMessageSerializer $serializer,
    ) {}

    /**
     * @return array{ok: true, message: array<string, mixed>}|array{ok: false, error: string}
     */
    public function __invoke(User $user, int $conversationId, int $messageId, string $newBody, MessagingService $messaging): array
    {
        if (! $user->conversations()->whereKey($conversationId)->exists()) {
            return ['ok' => false, 'error' => __('Unauthorized.')];
        }

        $message = Message::query()->find($messageId);
        if (! $message instanceof Message || (int) $message->conversation_id !== $conversationId) {
            return ['ok' => false, 'error' => __('Message not found.')];
        }

        $message->load('messageable');
        $sender = $message->messageable;
        if (! $sender instanceof User || (string) $sender->getKey() !== (string) $user->getKey()) {
            return ['ok' => false, 'error' => __('Unauthorized.')];
        }

        try {
            $fresh = $messaging->editMessage($message, $user, $newBody);
            $fresh->load(['messageable', 'attachments']);

            return [
                'ok' => true,
                'message' => $this->serializer->serializeForDispatch($fresh, $user, null),
            ];
        } catch (CannotMessageException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
