<?php

namespace Phunky\Restify\Actions;

use Binaryk\LaravelRestify\Actions\Action;
use Binaryk\LaravelRestify\Http\Requests\ActionRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Phunky\Actions\Chat\SendChatMessage;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessagingAttachments\AttachmentService;
use Phunky\Models\User;
use Phunky\Support\MessageAttachmentTypeRegistry;
use Symfony\Component\HttpFoundation\Response;

final class SendMessageAction extends Action
{
    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'integer', 'min:1'],
            'body' => ['nullable', 'string', 'max:65535'],
            'attachment_kind' => ['required', 'string', Rule::in(array_keys(MessageAttachmentTypeRegistry::definitions()))],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file'],
        ];
    }

    public function handle(ActionRequest $request): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        /** @var list<UploadedFile> $files */
        $files = array_values($request->file('attachments', []));

        $result = app(SendChatMessage::class)(
            $user,
            (int) $request->input('conversation_id'),
            (string) $request->input('body', ''),
            (string) $request->input('attachment_kind'),
            $files,
            app(MessagingService::class),
            app(AttachmentService::class),
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['message' => $result['message']], 201);
    }
}
