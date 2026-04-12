<?php

namespace Phunky\Livewire\Concerns;

use Phunky\Actions\Chat\ResolveAttachmentDisplayUrl;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessagingAttachments\Attachment as MessageAttachment;
use Phunky\Models\User;
use Phunky\Support\MessageAttachmentTypeRegistry;

trait SerializesChatMessages
{
    /**
     * @return array{id: int, body: string, sent_at: ?string, edited_at: ?string, sender_id: string, sender_name: string, is_me: bool, attachments: list<array{id: int, type: string, url: string, filename: string, mime_type: ?string, size: ?int}>}
     */
    protected function serializeMessage(Message $m): array
    {
        $sender = $m->messageable;
        $uid = auth()->id();

        $attachmentItems = [];
        if ($m->relationLoaded('attachments')) {
            foreach ($m->getRelation('attachments') as $row) {
                if (! $row instanceof MessageAttachment || ! MessageAttachmentTypeRegistry::has($row->type)) {
                    continue;
                }

                $attachmentItems[] = [
                    'id' => (int) $row->id,
                    'type' => $row->type,
                    'url' => (app(ResolveAttachmentDisplayUrl::class))($row),
                    'filename' => $row->filename,
                    'mime_type' => $row->mime_type,
                    'size' => $row->size !== null ? (int) $row->size : null,
                ];
            }
        }

        return [
            'id' => (int) $m->id,
            'body' => $m->body,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'edited_at' => $m->edited_at?->toIso8601String(),
            'sender_id' => $sender ? (string) $sender->getKey() : '',
            'sender_name' => $sender instanceof User ? $sender->name : __('Unknown'),
            'is_me' => $sender instanceof User && (string) $sender->getKey() === (string) $uid,
            'attachments' => $attachmentItems,
        ];
    }
}
