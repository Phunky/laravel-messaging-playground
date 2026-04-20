<?php

namespace Phunky\View\Composers;

use Illuminate\Contracts\View\View;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\Models\User;

/**
 * Injects the authenticated user's chat meta into the main app layout so the
 * blade template no longer runs an Eloquent pluck() inside a `@php` block.
 * The conversation-ids list is flattened to a comma string because it's only
 * consumed by the Echo bootstrap in JavaScript.
 */
final class ChatLayoutComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $view->with([
                'chatUserId' => null,
                'chatUserName' => null,
                'chatConversationIds' => '',
            ]);

            return;
        }

        /** @var class-string<Conversation> $conversationModel */
        $conversationModel = config('messaging.models.conversation');
        $table = (new $conversationModel)->getTable();

        $ids = $user->conversations()
            ->pluck($table.'.id')
            ->map(static fn ($id): string => (string) $id)
            ->implode(',');

        $view->with([
            'chatUserId' => (string) $user->getKey(),
            'chatUserName' => (string) ($user->name ?? ''),
            'chatConversationIds' => $ids,
        ]);
    }
}
