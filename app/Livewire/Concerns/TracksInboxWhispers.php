<?php

namespace Phunky\Livewire\Concerns;

use Phunky\Models\User;

trait TracksInboxWhispers
{
    /**
     * @var array<string, array<int, list<string>>>
     */
    protected array $inboxWhispersByKind = [
        'typing' => [],
        'recording' => [],
    ];

    /**
     * @var array<int, list<string>>
     */
    public array $typingByConversation = [];

    /**
     * @var array<int, list<string>>
     */
    public array $recordingByConversation = [];

    /**
     * @param  list<array{id: int|string, name: string}>  $users
     */
    protected function applyInboxWhisperUpdate(string $kind, int $conversationId, array $users): void
    {
        if (! in_array($kind, ['typing', 'recording'], true)) {
            return;
        }

        $selfKey = $this->currentInboxWhisperSelfKey();

        $names = array_values(array_filter(array_map(
            static function (array $row) use ($selfKey): ?string {
                $id = $row['id'] ?? null;
                $name = isset($row['name']) ? trim((string) $row['name']) : '';
                if ($id === null || $name === '' || ($selfKey !== null && (string) $id === $selfKey)) {
                    return null;
                }

                return $name;
            },
            $users,
        )));

        if ($names === []) {
            unset($this->inboxWhispersByKind[$kind][$conversationId]);
        } else {
            $this->inboxWhispersByKind[$kind][$conversationId] = $names;
        }

        $this->syncInboxWhisperPublicState();
    }

    protected function clearInboxWhispers(): void
    {
        $this->inboxWhispersByKind = [
            'typing' => [],
            'recording' => [],
        ];
        $this->syncInboxWhisperPublicState();
    }

    private function syncInboxWhisperPublicState(): void
    {
        $this->typingByConversation = $this->inboxWhispersByKind['typing'] ?? [];
        $this->recordingByConversation = $this->inboxWhispersByKind['recording'] ?? [];
    }

    private function currentInboxWhisperSelfKey(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? (string) $user->getKey() : null;
    }
}
