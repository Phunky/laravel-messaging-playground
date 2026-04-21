<?php

namespace Phunky\Livewire\Concerns;

use Phunky\Actions\Chat\ResolveAttachmentDisplayUrl;
use Phunky\LaravelMessaging\MessagingEventName;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\MessagingEvent;
use Phunky\LaravelMessaging\Models\Participant;
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
     * Stamp outbound read receipt display for serialized message rows using messaging_events (MessageRead).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function hydrateReadReceipts(array $rows, ?Conversation $conversation): array
    {
        if ($conversation === null || $rows === []) {
            return array_map(fn (array $row): array => $this->withDefaultReadReceipt($row), $rows);
        }

        $uid = auth()->id();
        if ($uid === null) {
            return array_map(fn (array $row): array => $this->withDefaultReadReceipt($row), $rows);
        }

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
        $messageMorph = (new $messageClass)->getMorphClass();

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
    private function withDefaultReadReceipt(array $row): array
    {
        if (! isset($row['read_receipt_display'])) {
            $row['read_receipt_display'] = ($row['is_me'] ?? false) ? 'sent' : 'hidden';
        }

        return $row;
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

    protected function stabilizeChatScroll(): void
    {
        $this->js('queueMicrotask(() => requestAnimationFrame(() => window.stabilizeChatScrollToBottom?.()))');
    }

    protected function revealChatBottomIfNearBottom(): void
    {
        $this->js('queueMicrotask(() => requestAnimationFrame(() => window.scrollChatToBottomIfNearBottom?.()))');
    }
}
