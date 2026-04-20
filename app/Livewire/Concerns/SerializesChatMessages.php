<?php

namespace Phunky\Livewire\Concerns;

use Phunky\Actions\Chat\ResolveAttachmentDisplayUrl;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessagingAttachments\Attachment as MessageAttachment;
use Phunky\Models\User;
use Phunky\Support\Chat\MessageViewModel;
use Phunky\Support\MessageAttachmentTypeRegistry;

trait SerializesChatMessages
{
    /**
     * Produce the JSON-serialisable message payload that the Livewire wire
     * protocol and broadcast events exchange. Kept as an array so existing
     * tests and JS listeners continue to see the same shape. Use
     * {@see self::messageViewModel()} when you want the typed DTO.
     *
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

    /**
     * Wrap a single serialized row in a {@see MessageViewModel} for template
     * rendering.
     *
     * @param  array<string, mixed>  $serialized
     */
    protected function messageViewModel(array $serialized): MessageViewModel
    {
        return MessageViewModel::fromArray($serialized);
    }

    /**
     * Wrap a list of serialized rows and stamp `isFirstOfDay` flags so
     * templates can insert date separators without any `@php` tracking.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<MessageViewModel>
     */
    protected function messageViewModels(array $rows): array
    {
        return MessageViewModel::listFromArray($rows);
    }
}
