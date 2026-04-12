@props([
    'url' => '',
    'filename' => '',
    'mimeType' => null,
    'size' => null,
    'variant' => 'mine',
])

@php
    use Phunky\Support\DocumentAttachmentIcon;
    use Illuminate\Support\Number;
    use Illuminate\Support\Str;

    $mime = $mimeType !== null && $mimeType !== '' ? (string) $mimeType : null;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if ($mime === 'application/pdf' || $ext === 'pdf') {
        $typeLabel = 'PDF';
    } elseif ($mime === 'application/msword' || $ext === 'doc') {
        $typeLabel = 'Word';
    } elseif ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || $ext === 'docx') {
        $typeLabel = 'Word';
    } elseif ($ext !== '') {
        $typeLabel = strtoupper($ext);
    } else {
        $typeLabel = __('Document');
    }

    $sizeLabel = $size !== null ? Number::fileSize((int) $size) : null;
    $metaParts = array_filter([$typeLabel, $sizeLabel]);
    $metaLine = implode(' • ', $metaParts);

    $isMine = $variant === 'mine';
    $fluxDocumentIcon = DocumentAttachmentIcon::resolve($mimeType, $filename);
    $downloadFilename = $filename !== '' ? Str::ascii($filename) : null;
@endphp

<div {{ $attributes->class(['w-full max-w-xs overflow-hidden rounded-xl shadow-md']) }}>
    <div
        @class([
            'flex items-start gap-2.5 rounded-xl px-2.5 py-2.5',
            'bg-[#1d3133]' => $isMine,
            'bg-zinc-200 dark:bg-zinc-600' => ! $isMine,
        ])
    >
        <div class="shrink-0 pt-0.5" aria-hidden="true">
            <flux:icon
                :icon="$fluxDocumentIcon"
                variant="outline"
                @class([
                    'size-8 shrink-0',
                    'text-[#8696a0]' => $isMine,
                    'text-zinc-500 dark:text-zinc-400' => ! $isMine,
                ])
            />
        </div>
        <div class="min-w-0 flex-1">
            <p
                @class([
                    'truncate text-sm font-medium leading-snug',
                    'text-white' => $isMine,
                    'text-zinc-900 dark:text-zinc-50' => ! $isMine,
                ])
            >
                {{ $filename }}
            </p>
            @if ($metaLine !== '')
                <p class="mt-0.5 text-xs leading-tight text-[#8696a0]">
                    {{ $metaLine }}
                </p>
            @endif
        </div>
        @if ($downloadFilename !== null)
            <flux:button
                :href="$url"
                download="{{ $downloadFilename }}"
                target="_blank"
                rel="noopener noreferrer"
                icon="arrow-down-tray"
                variant="subtle"
                :title="__('Download')"
                :aria-label="__('Download')"
            />
        @else
            <flux:button
                :href="$url"
                target="_blank"
                rel="noopener noreferrer"
                icon="arrow-down-tray"
                variant="subtle"
                :title="__('Download')"
                :aria-label="__('Download')"
            />
        @endif
    </div>
</div>
