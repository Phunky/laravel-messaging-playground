<?php

namespace Phunky\Support\Chat;

use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Wireable;
use Phunky\Support\DocumentAttachmentIcon;
use Phunky\Support\MessageAttachmentTypeRegistry;

/**
 * One attachment slot ready for rendering. Consumed by `message-type-*` Blade
 * components and `message-attachment-stack`.
 */
final readonly class AttachmentViewModel implements Wireable
{
    public function __construct(
        public int $id,
        public string $type,
        public string $url,
        public string $filename,
        public ?string $mimeType,
        public ?int $size,
    ) {}

    /**
     * @param  array{id: int|string, type: string, url: string, filename: string, mime_type: ?string, size: ?int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            type: (string) $data['type'],
            url: (string) ($data['url'] ?? ''),
            filename: (string) ($data['filename'] ?? ''),
            mimeType: isset($data['mime_type']) && $data['mime_type'] !== '' ? (string) $data['mime_type'] : null,
            size: isset($data['size']) && $data['size'] !== null && $data['size'] !== '' ? (int) $data['size'] : null,
        );
    }

    /**
     * @param  list<array{id: int|string, type: string, url: string, filename: string, mime_type: ?string, size: ?int}>  $rows
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

    /**
     * Accept a mixed list of raw attachment arrays and/or already-constructed
     * AttachmentViewModel instances and return a normalised list. Lets
     * anonymous Blade components pass whatever shape they have without any
     * inline branching.
     *
     * @param  list<self|array{id: int|string, type: string, url: string, filename: string, mime_type: ?string, size: ?int}>  $items
     * @return list<self>
     */
    public static function coerceList(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if ($item instanceof self) {
                $out[] = $item;

                continue;
            }

            if (is_array($item)) {
                $out[] = self::fromArray($item);
            }
        }

        return $out;
    }

    public const GROUP_IMAGES = 'images';

    public const GROUP_VIDEO = 'video';

    public const GROUP_VOICE = 'voice';

    public const GROUP_DOCUMENT = 'document';

    /**
     * Normalise raw serialized attachment rows and group them in a single call.
     *
     * @param  list<array{id: int|string, type: string, url: string, filename: string, mime_type: ?string, size: ?int}>  $rows
     * @return list<array{kind: string, items: list<self>}>
     */
    public static function groupFromArrays(array $rows): array
    {
        return self::group(self::listFromArray($rows));
    }

    /**
     * Groups adjacent images, stand-alone videos, voice notes, and documents
     * for the bubble renderer. Mirrors the rule set used by the conversation
     * media viewer.
     *
     * @param  list<self|array{id: int|string, type: string, url: string, filename: string, mime_type: ?string, size: ?int}>  $items
     * @return list<array{kind: string, items: list<self>}>
     */
    public static function group(array $items): array
    {
        $items = self::coerceList($items);

        /** @var list<array{kind: string, items: list<self>}> $groups */
        $groups = [];
        /** @var list<self> $imageRun */
        $imageRun = [];

        $flush = static function () use (&$groups, &$imageRun): void {
            if ($imageRun !== []) {
                $groups[] = ['kind' => self::GROUP_IMAGES, 'items' => $imageRun];
                $imageRun = [];
            }
        };

        foreach ($items as $item) {
            if ($item->isImageSlot()) {
                $imageRun[] = $item;

                continue;
            }

            if ($item->isVideoSlot()) {
                $flush();
                $groups[] = ['kind' => self::GROUP_VIDEO, 'items' => [$item]];

                continue;
            }

            $flush();

            if ($item->isVoiceNote()) {
                $groups[] = ['kind' => self::GROUP_VOICE, 'items' => [$item]];

                continue;
            }

            if ($item->isDocument()) {
                $groups[] = ['kind' => self::GROUP_DOCUMENT, 'items' => [$item]];
            }
        }

        $flush();

        return $groups;
    }

    /**
     * Converts a run of image attachments into ready-to-render grid cells,
     * choosing grid spans and injecting the "+N" overflow count on the 4th
     * tile when more than four images are present. Consumed by
     * `message-type-images`.
     *
     * @param  list<self|array{id: int|string, type: string, url: string, filename: string, mime_type: ?string, size: ?int}>  $items
     * @return list<array{attachment: self, span: string, overflow: int}>
     */
    public static function imageGridCells(array $items): array
    {
        $filled = array_values(array_filter(self::coerceList($items), static fn (self $item): bool => $item->hasUrl()));
        $total = count($filled);

        if ($total === 0) {
            return [];
        }

        $overflow = max(0, $total - 4);

        if ($overflow > 0) {
            $cells = [];
            foreach (array_slice($filled, 0, 4) as $index => $item) {
                $cells[] = [
                    'attachment' => $item,
                    'span' => 'col-span-1 row-span-1',
                    'overflow' => $index === 3 ? $overflow : 0,
                ];
            }

            return $cells;
        }

        $cells = [];
        foreach ($filled as $index => $item) {
            $cells[] = [
                'attachment' => $item,
                'span' => match (true) {
                    $total === 1 => 'col-span-2 row-span-2',
                    $total === 2 => 'col-span-1 row-span-2',
                    $total === 3 && $index === 2 => 'col-span-2 row-span-1',
                    default => 'col-span-1 row-span-1',
                },
                'overflow' => 0,
            ];
        }

        return $cells;
    }

    public function hasUrl(): bool
    {
        return $this->url !== '';
    }

    public function isKnownKind(): bool
    {
        return MessageAttachmentTypeRegistry::has($this->type);
    }

    /**
     * Old messages stored videos under `type=image` with a `video/*` mime; the
     * renderer still treats those as video slots.
     */
    public function isLegacyVideoAsImage(): bool
    {
        return $this->hasUrl()
            && $this->type === 'image'
            && $this->mimeType !== null
            && str_starts_with($this->mimeType, 'video/');
    }

    public function isImageSlot(): bool
    {
        return $this->hasUrl()
            && $this->type === 'image'
            && ! $this->isLegacyVideoAsImage()
            && ($this->mimeType === null || str_starts_with($this->mimeType, 'image/'));
    }

    public function isVideoSlot(): bool
    {
        if (! $this->hasUrl()) {
            return false;
        }

        if ($this->type === 'video' || $this->type === 'video_note') {
            return $this->mimeType === null || str_starts_with($this->mimeType, 'video/');
        }

        return $this->isLegacyVideoAsImage();
    }

    public function isVoiceNote(): bool
    {
        return $this->type === 'voice_note' && $this->isKnownKind() && $this->hasUrl();
    }

    public function isVideoNote(): bool
    {
        return $this->type === 'video_note' && $this->isKnownKind() && $this->hasUrl();
    }

    public function isDocument(): bool
    {
        return $this->type === 'document' && $this->isKnownKind() && $this->hasUrl();
    }

    public function isGif(): bool
    {
        return $this->mimeType === 'image/gif';
    }

    /**
     * Flux icon slug chosen from mime/extension config.
     */
    public function fluxDocumentIcon(): string
    {
        return DocumentAttachmentIcon::resolve($this->mimeType, $this->filename);
    }

    /**
     * Uppercase ext / friendly label for documents ("PDF", "Word", "JSON").
     */
    public function documentTypeLabel(): string
    {
        $mime = $this->mimeType;
        $ext = strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));

        if ($mime === 'application/pdf' || $ext === 'pdf') {
            return 'PDF';
        }

        if ($mime === 'application/msword' || $ext === 'doc') {
            return 'Word';
        }

        if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || $ext === 'docx') {
            return 'Word';
        }

        if ($ext !== '') {
            return strtoupper($ext);
        }

        return __('Document');
    }

    public function sizeLabel(): ?string
    {
        return $this->size !== null ? Number::fileSize($this->size) : null;
    }

    public function documentMetaLine(): string
    {
        $parts = array_values(array_filter([$this->documentTypeLabel(), $this->sizeLabel()]));

        return implode(' • ', $parts);
    }

    public function downloadFilename(): ?string
    {
        return $this->filename !== '' ? Str::ascii($this->filename) : null;
    }

    public function videoPosterPreload(): string
    {
        return VideoPosterSettings::preload($this->mimeType, $this->type);
    }

    public function videoPosterDataMimeType(): string
    {
        return VideoPosterSettings::dataMimeTypeAttribute($this->mimeType);
    }

    /**
     * Payload dispatched to `message-pane-open-media-viewer`. When no message
     * id is known the `messageId` key is omitted entirely so the JSON output
     * mirrors the pre-refactor behaviour exactly.
     *
     * @return array{attachmentId: int, messageId?: int}
     */
    public function openMediaPayload(?int $messageId): array
    {
        $payload = ['attachmentId' => $this->id];
        if ($messageId !== null) {
            $payload['messageId'] = $messageId;
        }

        return $payload;
    }

    /**
     * @return array{id: int, type: string, url: string, filename: string, mime_type: ?string, size: ?int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'url' => $this->url,
            'filename' => $this->filename,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
        ];
    }

    public function toLivewire(): array
    {
        return $this->toArray();
    }

    public static function fromLivewire($value): self
    {
        /** @var array{id: int|string, type: string, url: string, filename: string, mime_type: ?string, size: ?int} $value */
        return self::fromArray($value);
    }
}
