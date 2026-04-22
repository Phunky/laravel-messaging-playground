<?php

namespace Phunky\Restify\Actions;

use Binaryk\LaravelRestify\Actions\Action;
use Binaryk\LaravelRestify\Http\Requests\ActionRequest;
use Phunky\Actions\Chat\ListThreadMessages;
use Phunky\Models\User;
use Symfony\Component\HttpFoundation\Response;

final class ThreadMessagesAction extends Action
{
    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'integer', 'min:1'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    public function handle(ActionRequest $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $payload = app(ListThreadMessages::class)(
            $user,
            (int) $request->input('conversation_id'),
            $request->input('cursor'),
        );

        return response()->json($payload);
    }
}
