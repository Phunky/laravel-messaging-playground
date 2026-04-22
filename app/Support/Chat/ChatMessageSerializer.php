<?php

namespace Phunky\Support\Chat;

use Phunky\Actions\Chat\ResolveAttachmentDisplayUrl;
use Phunky\LaravelMessaging\MessagingEventName;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\MessagingEvent;
use Phunky\LaravelMessaging\Models\Participant;
use Phunky\LaravelMessagingAttachments\Attachment as MessageAttachment;
use Phunky\Models\User;
use Phunky\Support\MessageAttachmentTypeRegistry;

final class ChatMessageSerializer
{
    public function __construct(
        private ResolveAttachmentDisplayUrl $resolveAttachmentDisplayUrl,
    ) {}

    /**
     * @return array{id: int, body: string, sent_at: ?string, edited_at: ?string, sender_id: string, sender_name: string, is_me: bool, attachments: list<array{id: int, type: string, url: string, filename: string, mime_type: ?string, size: ?int}>}
     */
    public function serialize(Message $m, User $viewer): array
    {
        $sender = $m->messageable;
        $uid = $viewer->getKey();

        $attachmentItems = [];
        if ($m->relationLoaded('attachments')) {
            foreach ($m->getRelation('attachments') as $row) {
                if (! $row instanceof MessageAttachment || ! MessageAttachmentTypeRegistry::has($row->type)) {
                    continue;
                }

                $attachmentItems[] = [
                    'id' => (int) $row->id,
                    'type' => $row->type,
                    'url' => ($this->resolveAttachmentDisplayUrl)($row),
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
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function hydrateReadReceipts(array $rows, ?Conversation $conversation, User $viewer): array
    {
        if ($conversation === null || $rows === []) {
            return array_map(fn (array $row): array => $this->withDefaultReadReceipt($row), $rows);
        }

        $uid = $viewer->getKey();

        /** @var class-string<Participant> $participantClass */
        $participantClass = config('messaging.models.participant');
        $userMorph = (new User)->getMorphClass();

        $otherParticipantIds = $participantClass::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('messageable_type', $userMorph)
            ->where('messageable_id', '!=', $uid)
            ->pluck('id');

        $required = $otherParticipantIds->count();
        $myMessageIds = collect($rows)
            ->filter(static fn (array $r): bool => (bool) ($r['is_me'] ?? false))
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($myMessageIds === [] || $required === 0) {
            return array_map(fn (array $row): array => $this->withDefaultReadReceipt($row), $rows);
        }

        /** @var class-string<Message> $messageClass */
        $messageClass = config('messaging.models.message');
        $messageInstance = new $messageClass;
        $messageMorph = $messageInstance->getMorphClass();

        /** @var class-string<MessagingEvent> $eventClass */
        $eventClass = config('messaging.models.event');

        $counts = $eventClass::query()
            ->where('event', MessagingEventName::MessageRead)
            ->where('subject_type', $messageMorph)
            ->whereIn('subject_id', $myMessageIds)
            ->whereIn('participant_id', $otherParticipantIds->all())
            ->selectRaw('subject_id, count(distinct participant_id) as read_count')
            ->groupBy('subject_id')
            ->pluck('read_count', 'subject_id');

        return array_map(function (array $row) use ($counts, $required): array {
            if (! ($row['is_me'] ?? false)) {
                $row['read_receipt_display'] = 'hidden';

                return $row;
            }

            $id = (int) $row['id'];
            $readCount = (int) ($counts[$id] ?? 0);
            $isDm = $required === 1;
            if ($isDm) {
                $row['read_receipt_display'] = $readCount >= 1 ? 'read' : 'sent';
            } else {
                $row['read_receipt_display'] = ($readCount >= $required) ? 'read' : 'sent';
            }

            return $row;
        }, $rows);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function withDefaultReadReceipt(array $row): array
    {
        if (! isset($row['read_receipt_display'])) {
            $row['read_receipt_display'] = ($row['is_me'] ?? false) ? 'sent' : 'hidden';
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForDispatch(Message $message, User $viewer, ?Conversation $conversation): array
    {
        $conversation ??= Conversation::query()->find($message->conversation_id);
        $rows = $this->hydrateReadReceipts(
            [$this->serialize($message, $viewer)],
            $conversation,
            $viewer,
        );

        return $rows[0] ?? $this->serialize($message, $viewer);
    }
}
