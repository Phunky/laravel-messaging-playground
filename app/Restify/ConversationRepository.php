<?php

namespace Phunky\Restify;

use Binaryk\LaravelRestify\Attributes\Model as RestifyModel;
use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Repositories\Repository;
use Phunky\LaravelMessaging\Facades\Messenger;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\Models\User;
use Phunky\Restify\Actions\ConversationInboxAction;
use Phunky\Restify\Actions\ConversationMediaAction;
use Phunky\Restify\Actions\MarkConversationReadAction;

#[RestifyModel(Conversation::class)]
final class ConversationRepository extends Repository
{
    public static function indexQuery(RestifyRequest $request, $query)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $query->whereRaw('0=1');
        }

        $model = $query->getModel();

        return $query->whereIn(
            $model->getQualifiedKeyName(),
            Messenger::conversationsFor($user)->select($model->getQualifiedKeyName()),
        );
    }

    public function fields(RestifyRequest $request): array
    {
        return [
            field('id')->readonly(),
        ];
    }

    public function actions(RestifyRequest $request): array
    {
        return [
            ConversationInboxAction::new()->standalone()->onlyOnIndex(),
            MarkConversationReadAction::new()->standalone()->onlyOnIndex(),
            ConversationMediaAction::new()->standalone()->onlyOnIndex(),
        ];
    }
}
