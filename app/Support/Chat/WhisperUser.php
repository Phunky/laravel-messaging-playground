<?php

namespace Phunky\Support\Chat;

use Livewire\Wireable;

/**
 * One typing/recording user row, passed between the inbox, the pane, and the
 * thread via Livewire Wireable so child SFCs can declare `list<WhisperUser>`
 * typed props.
 */
final readonly class WhisperUser implements Wireable
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    /**
     * Build from an inbound event payload row. Returns null when the row is
     * unusable (missing id, blank name) so callers can array_filter the list.
     *
     * @param  array{id?: int|string|null, name?: string|null}  $row
     */
    public static function fromArray(array $row): ?self
    {
        $id = $row['id'] ?? null;
        $name = isset($row['name']) ? trim((string) $row['name']) : '';

        if ($id === null || $name === '') {
            return null;
        }

        return new self((int) $id, $name);
    }

    /**
     * @param  list<array{id?: int|string|null, name?: string|null}>  $rows
     * @return list<self>
     */
    public static function listFromArray(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $vm = self::fromArray($row);
            if ($vm !== null) {
                $out[] = $vm;
            }
        }

        return $out;
    }

    /**
     * Accepts either `list<array{id, name}>` rows (pane/thread payloads) or
     * `list<string>` plain names (inbox state) and returns a clean list of
     * non-blank display names.
     *
     * @param  list<array{id?: int|string|null, name?: string|null}|string|null>  $rows
     * @return list<string>
     */
    public static function namesFrom(array $rows): array
    {
        $names = [];
        foreach ($rows as $row) {
            if (is_string($row)) {
                $trimmed = trim($row);
                if ($trimmed !== '') {
                    $names[] = $trimmed;
                }

                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $vm = self::fromArray($row);
            if ($vm !== null) {
                $names[] = $vm->name;
            }
        }

        return $names;
    }

    /**
     * @return array{id: int, name: string}
     */
    public function toLivewire(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }

    public static function fromLivewire($value): self
    {
        /** @var array{id: int|string, name: string} $value */
        return new self((int) $value['id'], (string) $value['name']);
    }
}
