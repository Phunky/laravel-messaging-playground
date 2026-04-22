<?php

namespace Phunky\Restify\Actions;

use Binaryk\LaravelRestify\Actions\Action;
use Binaryk\LaravelRestify\Http\Requests\ActionRequest;
use Phunky\Actions\Chat\ListConversationInboxRows;
use Phunky\Models\User;
use Symfony\Component\HttpFoundation\Response;

final class ConversationInboxAction extends Action
{
    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string'],
        ];
    }

    public function handle(ActionRequest $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $payload = app(ListConversationInboxRows::class)($user, $request->input('cursor'));

        return response()->json($payload);
    }
}
