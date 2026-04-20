@props([
    'attachment' => null,
    'variant' => 'mine',
])

{{--
    Expects a Phunky\Support\Chat\AttachmentViewModel instance. File-type label,
    icon, size label, and downloadable filename all come from the DTO so this
    template stays free of PHP logic.
--}}

<div {{ $attributes->class(['w-full max-w-xs overflow-hidden rounded-xl shadow-md']) }}>
    <div
        @class([
            'flex items-start gap-2.5 rounded-xl px-2.5 py-2.5',
            'bg-[#1d3133]' => $variant === 'mine',
            'bg-zinc-200 dark:bg-zinc-600' => $variant !== 'mine',
        ])
    >
        <div class="shrink-0 pt-0.5" aria-hidden="true">
            <flux:icon
                :icon="$attachment->fluxDocumentIcon()"
                variant="outline"
                @class([
                    'size-8 shrink-0',
                    'text-[#8696a0]' => $variant === 'mine',
                    'text-zinc-500 dark:text-zinc-400' => $variant !== 'mine',
                ])
            />
        </div>
        <div class="min-w-0 flex-1">
            <p
                @class([
                    'truncate text-sm font-medium leading-snug',
                    'text-white' => $variant === 'mine',
                    'text-zinc-900 dark:text-zinc-50' => $variant !== 'mine',
                ])
            >
                {{ $attachment->filename }}
            </p>
            @if ($attachment->documentMetaLine() !== '')
                <p class="mt-0.5 text-xs leading-tight text-[#8696a0]">
                    {{ $attachment->documentMetaLine() }}
                </p>
            @endif
        </div>
        <flux:button
            :href="$attachment->url"
            :download="$attachment->downloadFilename()"
            target="_blank"
            rel="noopener noreferrer"
            icon="arrow-down-tray"
            variant="subtle"
            :title="__('Download')"
            :aria-label="__('Download')"
        />
    </div>
</div>
