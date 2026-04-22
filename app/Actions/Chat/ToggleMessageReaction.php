<?php

namespace Phunky\Actions\Chat;

use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessagingReactions\Exceptions\ReactionException;
use Phunky\LaravelMessagingReactions\Reaction;
use Phunky\LaravelMessagingReactions\ReactionService;
use Phunky\Models\User;

final class ToggleMessageReaction
{
    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function __invoke(
        User $user,
        int $conversationId,
        int $messageId,
        string $reaction,
        ReactionService $reactions,
        MessagingService $messaging,
    ): array {
        if (! $user->conversations()->whereKey($conversationId)->exists()) {
            return ['ok' => false, 'error' => __('Unauthorized.')];
        }

        $conversation = Conversation::query()->find($conversationId);
        if (! $conversation instanceof Conversation) {
            return ['ok' => false, 'error' => __('Conversation not found.')];
        }

        $message = Message::query()->find($messageId);
        if (! $message instanceof Message || (int) $message->conversation_id !== $conversationId) {
            return ['ok' => false, 'error' => __('Message not found.')];
        }

        try {
            $participant = $messaging->findParticipant($conversation, $user);
            $existing = $participant
                ? Reaction::query()
                    ->where('message_id', $message->getKey())
                    ->where('participant_id', $participant->getKey())
                    ->value('reaction')
                : null;

            if ($existing === $reaction) {
                $reactions->removeReaction($message, $user);
            } else {
                $reactions->react($message, $user, $reaction);
            }

            return ['ok' => true];
        } catch (ReactionException) {
            return ['ok' => true];
        }
    }
}
