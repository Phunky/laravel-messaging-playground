<?php

namespace Phunky\Restify\Actions;

use Binaryk\LaravelRestify\Actions\Action;
use Binaryk\LaravelRestify\Http\Requests\ActionRequest;
use Phunky\Actions\Chat\DeleteChatMessage;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\Models\User;
use Symfony\Component\HttpFoundation\Response;

final class DeleteMessageAction extends Action
{
    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'integer', 'min:1'],
            'message_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function handle(ActionRequest $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $result = app(DeleteChatMessage::class)(
            $user,
            (int) $request->input('conversation_id'),
            (int) $request->input('message_id'),
            app(MessagingService::class),
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->noContent();
    }
}
