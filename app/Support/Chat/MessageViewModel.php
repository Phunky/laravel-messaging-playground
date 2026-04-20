<?php

namespace Phunky\Support\Chat;

use Livewire\Wireable;

/**
 * Ready-to-render message row. Templates only echo scalar fields and iterate
 * pre-built attachment groups; no Carbon, no Str, no array_filter in blade.
 */
final readonly class MessageViewModel implements Wireable
{
    public const KIND_IMAGES = AttachmentViewModel::GROUP_IMAGES;

    public const KIND_VIDEO = AttachmentViewModel::GROUP_VIDEO;

    public const KIND_VOICE = AttachmentViewModel::GROUP_VOICE;

    public const KIND_DOCUMENT = AttachmentViewModel::GROUP_DOCUMENT;

    /**
     * @param  list<AttachmentViewModel>  $attachments
     */
    public function __construct(
        public int $id,
        public string $body,
        public ?string $sentAt,
        public ?string $editedAt,
        public string $senderId,
        public string $senderName,
        public bool $isMe,
        public array $attachments,
        public bool $isFirstOfDay = false,
    ) {}

    /**
     * @param  array{id: int|string, body: string, sent_at: ?string, edited_at: ?string, sender_id: string, sender_name: string, is_me: bool, attachments?: list<array{id: int|string, type: string, url: string, filename: string, mime_type: ?string, size: ?int}>, is_first_of_day?: bool}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            body: (string) ($data['body'] ?? ''),
            sentAt: $data['sent_at'] ?? null,
            editedAt: $data['edited_at'] ?? null,
            senderId: (string) ($data['sender_id'] ?? ''),
            senderName: (string) ($data['sender_name'] ?? __('Unknown')),
            isMe: (bool) ($data['is_me'] ?? false),
            attachments: AttachmentViewModel::listFromArray($data['attachments'] ?? []),
            isFirstOfDay: (bool) ($data['is_first_of_day'] ?? false),
        );
    }

    /**
     * Wrap a list of serialized message arrays and stamp `is_first_of_day`
     * based on app-timezone day buckets so templates don't track `$prevDate`.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<self>
     */
    public static function listFromArray(array $rows): array
    {
        $out = [];
        $prevBucket = null;
        foreach ($rows as $row) {
            $bucket = ChatTimestamp::dayBucket($row['sent_at'] ?? null);
            $row['is_first_of_day'] = $bucket !== null && $bucket !== $prevBucket;
            $out[] = self::fromArray($row);
            if ($bucket !== null) {
                $prevBucket = $bucket;
            }
        }

        return $out;
    }

    public function formattedSentAt(): string
    {
        return ChatTimestamp::bubbleTime($this->sentAt);
    }

    public function formattedEditedAt(): string
    {
        return ChatTimestamp::bubbleEditedTime($this->editedAt);
    }

    public function isEdited(): bool
    {
        return $this->editedAt !== null && $this->editedAt !== '';
    }

    public function hasBody(): bool
    {
        return $this->body !== '';
    }

    public function hasAttachments(): bool
    {
        return $this->attachments !== [];
    }

    public function dayBucket(): ?string
    {
        return ChatTimestamp::dayBucket($this->sentAt);
    }

    /**
     * Groups adjacent images, stand-alone videos, voice notes, and documents
     * for the bubble renderer.
     *
     * @return list<array{kind: string, items: list<AttachmentViewModel>}>
     */
    public function attachmentGroups(): array
    {
        return AttachmentViewModel::group($this->attachments);
    }

    /**
     * @return array{id: int, body: string, sent_at: ?string, edited_at: ?string, sender_id: string, sender_name: string, is_me: bool, attachments: list<array{id: int, type: string, url: string, filename: string, mime_type: ?string, size: ?int}>, is_first_of_day: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'sent_at' => $this->sentAt,
            'edited_at' => $this->editedAt,
            'sender_id' => $this->senderId,
            'sender_name' => $this->senderName,
            'is_me' => $this->isMe,
            'attachments' => array_map(
                static fn (AttachmentViewModel $a): array => $a->toArray(),
                $this->attachments,
            ),
            'is_first_of_day' => $this->isFirstOfDay,
        ];
    }

    public function toLivewire(): array
    {
        return $this->toArray();
    }

    public static function fromLivewire($value): self
    {
        /** @var array<string, mixed> $value */
        return self::fromArray($value);
    }
}
