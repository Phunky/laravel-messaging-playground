<?php

namespace Phunky\Restify\Actions;

use Binaryk\LaravelRestify\Actions\Action;
use Binaryk\LaravelRestify\Http\Requests\ActionRequest;
use Phunky\Actions\Chat\MarkConversationRead;
use Phunky\Models\User;
use Symfony\Component\HttpFoundation\Response;

final class MarkConversationReadAction extends Action
{
    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function handle(ActionRequest $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $conversationId = (int) $request->input('conversation_id');
        $ok = app(MarkConversationRead::class)($user, $conversationId);

        if (! $ok) {
            abort(403);
        }

        return response()->json(['ok' => true]);
    }
}
