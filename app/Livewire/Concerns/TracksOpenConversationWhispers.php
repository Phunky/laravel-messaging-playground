<?php

namespace Phunky\Livewire\Concerns;

use Phunky\Models\User;

trait TracksOpenConversationWhispers
{
    /**
     * @var array<string, list<array{id: int, name: string}>>
     */
    protected array $openConversationWhispersByKind = [
        'typing' => [],
        'recording' => [],
    ];

    /**
     * @var list<array{id: int, name: string}>
     */
    public array $typingUsers = [];

    /**
     * @var list<array{id: int, name: string}>
     */
    public array $recordingUsers = [];

    /**
     * @param  list<array{id: int|string, name: string}>  $users
     */
    protected function applyOpenConversationWhisperUpdate(string $kind, int $conversationId, array $users): void
    {
        if (! in_array($kind, ['typing', 'recording'], true)) {
            return;
        }

        if ($this->conversationId === null || (int) $conversationId !== (int) $this->conversationId) {
            return;
        }

        $selfKey = $this->currentWhisperSelfKey();

        $normalised = array_values(array_filter(array_map(
            static function (array $row): ?array {
                $id = $row['id'] ?? null;
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($id === null || $name === '') {
                    return null;
                }

                return ['id' => (int) $id, 'name' => $name];
            },
            $users,
        ), static fn (?array $row): bool => $row !== null && ($selfKey === null || (string) $row['id'] !== $selfKey)));

        $this->openConversationWhispersByKind[$kind] = $normalised;
        $this->syncOpenConversationWhisperPublicState();
    }

    protected function clearOpenConversationWhispers(): void
    {
        $this->openConversationWhispersByKind = [
            'typing' => [],
            'recording' => [],
        ];
        $this->syncOpenConversationWhisperPublicState();
    }

    private function syncOpenConversationWhisperPublicState(): void
    {
        $this->typingUsers = $this->openConversationWhispersByKind['typing'] ?? [];
        $this->recordingUsers = $this->openConversationWhispersByKind['recording'] ?? [];
    }

    private function currentWhisperSelfKey(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? (string) $user->getKey() : null;
    }
}
