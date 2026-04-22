<?php

namespace Phunky\Restify\Actions;

use Binaryk\LaravelRestify\Actions\Action;
use Binaryk\LaravelRestify\Http\Requests\ActionRequest;
use Phunky\Actions\Chat\ToggleMessageReaction;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessagingReactions\ReactionService;
use Phunky\Models\User;
use Symfony\Component\HttpFoundation\Response;

final class ToggleReactionAction extends Action
{
    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'integer', 'min:1'],
            'message_id' => ['required', 'integer', 'min:1'],
            'reaction' => ['required', 'string', 'max:64'],
        ];
    }

    public function handle(ActionRequest $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $result = app(ToggleMessageReaction::class)(
            $user,
            (int) $request->input('conversation_id'),
            (int) $request->input('message_id'),
            (string) $request->input('reaction'),
            app(ReactionService::class),
            app(MessagingService::class),
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['ok' => true]);
    }
}
