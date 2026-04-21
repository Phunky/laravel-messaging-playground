<?php

namespace Phunky\Actions\Chat;

use Illuminate\Database\Eloquent\Model;
use Phunky\LaravelMessagingAttachments\Attachment as MessageAttachment;
use Phunky\Models\User;
use Phunky\Support\ConversationMediaAttachmentFilter;

final class LoadConversationMediaForViewer
{
    public function __construct(
        private ResolveAttachmentDisplayUrl $resolveAttachmentDisplayUrl,
    ) {}

    /**
     * @return list<array{id: int, type: string, attachment_type: string, url: string, mime_type: ?string, filename: string, message_id: int}>
     */
    public function __invoke(User $user, int $conversationId, ?int $messageId = null): array
    {
        if (! $user->conversations()->whereKey($conversationId)->exists()) {
            return [];
        }

        /** @var class-string<Model> $messageClass */
        $messageClass = config('messaging.models.message');
        $messageInstance = new $messageClass;
        $messagesTable = $messageInstance->getTable();
        $attachmentsTable = (new MessageAttachment)->getTable();

        $query = MessageAttachment::query()
            ->select("{$attachmentsTable}.*")
            ->join($messagesTable, "{$messagesTable}.id", '=', "{$attachmentsTable}.message_id")
            ->where("{$attachmentsTable}.conversation_id", $conversationId)
            ->whereIn("{$attachmentsTable}.type", ['image', 'video', 'video_note']);

        if ($messageId !== null) {
            $query->where("{$attachmentsTable}.message_id", $messageId);
        }

        if ($messageId !== null) {
            $query->orderBy("{$attachmentsTable}.id");
        } else {
            $query
                ->orderBy("{$messagesTable}.sent_at")
                ->orderBy("{$messagesTable}.id")
                ->orderBy("{$attachmentsTable}.id");
        }

        $rows = $query->get();

        $out = [];
        foreach ($rows as $attachment) {
            if (! $attachment instanceof MessageAttachment) {
                continue;
            }

            if (! ConversationMediaAttachmentFilter::isViewerMediaSlot($attachment)) {
                continue;
            }

            $out[] = [
                'id' => (int) $attachment->id,
                'type' => ConversationMediaAttachmentFilter::viewerDisplayType($attachment),
                'attachment_type' => (string) $attachment->type,
                'url' => ($this->resolveAttachmentDisplayUrl)($attachment),
                'mime_type' => $attachment->mime_type,
                'filename' => $attachment->filename,
                'message_id' => (int) $attachment->message_id,
            ];
        }

        return $out;
    }
}
