<?php

namespace Phunky\Support\Chat;

use Livewire\Wireable;

/**
 * One row in the inbox (conversation list). Wraps the raw associative array
 * produced by the conversation list Livewire component (`formatRow()`) so
 * the template can iterate typed fields without any @php block.
 */
final readonly class ConversationRowViewModel implements Wireable
{
    /**
     * @param  list<int>  $otherParticipantIds
     */
    public function __construct(
        public int $conversationId,
        public string $title,
        public string $subtitle,
        public string $formattedTime,
        public int $unreadCount,
        public bool $isGroup,
        public ?string $updatedAt,
        public array $otherParticipantIds,
    ) {}

    /**
     * @param  array{conversation_id: int, title: string, subtitle: string, formatted_time?: string, unread_count: int, is_group: bool, updated_at: ?string, other_participant_ids: list<int>}  $row
     */
    public static function fromArray(array $row): self
    {
        $updatedAt = $row['updated_at'] ?? null;
        $formatted = $row['formatted_time'] ?? null;
        if ($formatted === null || $formatted === '') {
            $formatted = ChatTimestamp::inbox(is_string($updatedAt) ? $updatedAt : null);
        }

        return new self(
            conversationId: (int) $row['conversation_id'],
            title: (string) ($row['title'] ?? ''),
            subtitle: (string) ($row['subtitle'] ?? ''),
            formattedTime: (string) $formatted,
            unreadCount: (int) ($row['unread_count'] ?? 0),
            isGroup: (bool) ($row['is_group'] ?? false),
            updatedAt: is_string($updatedAt) ? $updatedAt : null,
            otherParticipantIds: array_map('intval', $row['other_participant_ids'] ?? []),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<self>
     */
    public static function listFromArray(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::fromArray($row);
        }

        return $out;
    }

    public function hasUnread(): bool
    {
        return $this->unreadCount > 0;
    }

    public function unreadBadge(): string
    {
        return $this->unreadCount > 99 ? '99+' : (string) $this->unreadCount;
    }

    /**
     * @return array{conversation_id: int, title: string, subtitle: string, formatted_time: string, unread_count: int, is_group: bool, updated_at: ?string, other_participant_ids: list<int>}
     */
    public function toArray(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'formatted_time' => $this->formattedTime,
            'unread_count' => $this->unreadCount,
            'is_group' => $this->isGroup,
            'updated_at' => $this->updatedAt,
            'other_participant_ids' => $this->otherParticipantIds,
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
