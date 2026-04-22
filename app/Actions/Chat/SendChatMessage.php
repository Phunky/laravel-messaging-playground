<?php

namespace Phunky\Actions\Chat;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Phunky\LaravelMessaging\Exceptions\CannotMessageException;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessagingAttachments\AttachmentService;
use Phunky\Models\User;
use Phunky\Support\Chat\ChatMessageSerializer;
use Phunky\Support\MessageAttachmentTypeRegistry;
use Throwable;

final class SendChatMessage
{
    public function __construct(
        private ChatMessageSerializer $serializer,
    ) {}

    /**
     * @param  list<UploadedFile|TemporaryUploadedFile>  $uploadedFiles
     * @return array{ok: true, message: array<string, mixed>}|array{ok: false, error: string}
     */
    public function __invoke(
        User $user,
        int $conversationId,
        string $body,
        string $attachmentKind,
        array $uploadedFiles,
        MessagingService $messaging,
        AttachmentService $attachmentService,
    ): array {
        if (! $user->conversations()->whereKey($conversationId)->exists()) {
            return ['ok' => false, 'error' => __('Unauthorized.')];
        }

        $conversation = Conversation::query()->find($conversationId);
        if (! $conversation instanceof Conversation) {
            return ['ok' => false, 'error' => __('Conversation not found.')];
        }

        if ($uploadedFiles !== [] && ! MessageAttachmentTypeRegistry::has($attachmentKind)) {
            return ['ok' => false, 'error' => __('Invalid attachment type.')];
        }

        $data = [
            'body' => $body,
            'attachment_kind' => $attachmentKind,
            'pendingFiles' => $uploadedFiles,
        ];

        $rules = [
            'body' => ['nullable', 'string', 'max:65535'],
            'attachment_kind' => ['required', 'string', Rule::in(array_keys(MessageAttachmentTypeRegistry::definitions()))],
        ];

        if ($uploadedFiles !== []) {
            $rules = array_merge($rules, MessageAttachmentTypeRegistry::validationRules($attachmentKind));
        } else {
            $rules['pendingFiles'] = ['nullable', 'array'];
        }

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            return ['ok' => false, 'error' => $validator->errors()->first() ?? __('Validation failed.')];
        }

        $trimmed = trim($body);
        if ($trimmed === '' && $uploadedFiles === []) {
            return ['ok' => false, 'error' => __('Please enter a message or add an attachment.')];
        }

        $message = null;

        try {
            $message = $messaging->sendMessage($conversation, $user, $trimmed);

            $diskName = config('messaging.media_disk');
            $attachmentRows = [];

            foreach ($uploadedFiles as $file) {
                if (! $this->isStorableUpload($file)) {
                    continue;
                }

                $directory = sprintf(
                    'messaging/%s/%s',
                    $conversation->getKey(),
                    $message->getKey(),
                );

                $storedPath = $file->store($directory, $diskName);

                $attachmentRows[] = [
                    'type' => $attachmentKind,
                    'path' => $storedPath,
                    'filename' => $file->getClientOriginalName(),
                    'disk' => $diskName,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }

            if ($attachmentRows !== []) {
                $attachmentService->attachMany($message, $user, $attachmentRows);
            }

            $message->load(['messageable', 'attachments']);

            return [
                'ok' => true,
                'message' => $this->serializer->serializeForDispatch($message, $user, $conversation),
            ];
        } catch (CannotMessageException $e) {
            if ($message instanceof Message) {
                try {
                    $messaging->deleteMessage($message, $user);
                } catch (Throwable) {
                }
            }

            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            if ($message instanceof Message) {
                try {
                    $messaging->deleteMessage($message, $user);
                } catch (Throwable) {
                }
            }

            report($e);

            return ['ok' => false, 'error' => __('Could not send your message. Please try again.')];
        }
    }

    private function isStorableUpload(mixed $file): bool
    {
        if ($file instanceof TemporaryUploadedFile) {
            return true;
        }

        return $file instanceof UploadedFile && $file->isValid();
    }
}
