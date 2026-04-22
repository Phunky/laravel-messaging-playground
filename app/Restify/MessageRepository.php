<?php

namespace Phunky\Restify;

use Binaryk\LaravelRestify\Attributes\Model as RestifyModel;
use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Repositories\Repository;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\Models\User;
use Phunky\Restify\Actions\DeleteMessageAction;
use Phunky\Restify\Actions\EditMessageAction;
use Phunky\Restify\Actions\SendMessageAction;
use Phunky\Restify\Actions\ThreadMessagesAction;
use Phunky\Restify\Actions\ToggleReactionAction;

#[RestifyModel(Message::class)]
final class MessageRepository extends Repository
{
    public static array $match = [
        'conversation_id',
    ];

    public static function indexQuery(RestifyRequest $request, $query)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $query->whereRaw('0=1');
        }

        $conversationId = (int) $request->query('conversation_id', 0);
        if ($conversationId < 1 || ! $user->conversations()->whereKey($conversationId)->exists()) {
            return $query->whereRaw('0=1');
        }

        return $query->where(
            $query->getModel()->qualifyColumn('conversation_id'),
            $conversationId
        );
    }

    public function fields(RestifyRequest $request): array
    {
        return [
            field('id')->readonly(),
            field('conversation_id')->readonly(),
        ];
    }

    public function actions(RestifyRequest $request): array
    {
        return [
            ThreadMessagesAction::new()->standalone()->onlyOnIndex(),
            SendMessageAction::new()->standalone()->onlyOnIndex(),
            ToggleReactionAction::new()->standalone()->onlyOnIndex(),
            EditMessageAction::new()->standalone()->onlyOnIndex(),
            DeleteMessageAction::new()->standalone()->onlyOnIndex(),
        ];
    }
}
