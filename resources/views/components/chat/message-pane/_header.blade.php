<div
    class="relative z-20 shrink-0 border-b border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900"
>
    <div class="flex min-w-0 items-center gap-3">
        @if ($conversationId !== null)
            <flux:button
                type="button"
                variant="ghost"
                size="sm"
                icon="chevron-left"
                wire:click="navigateBackToInbox"
                class="-ml-2 shrink-0 lg:hidden text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
            >
                <span class="sr-only">{{ __('Back') }}</span>
            </flux:button>
            <flux:avatar
                :name="$headerTitle"
                color="auto"
                color:seed="{{ $conversationId }}"
                size="sm"
            />
        @endif
        <flux:heading size="md" level="2" class="min-w-0 truncate">{{ $headerTitle }}</flux:heading>
        @if ($isGroup)
            <flux:badge size="sm" color="zinc">{{ __('Group') }}</flux:badge>
        @endif
        <flux:spacer />
        @if ($conversationId !== null && $conversationHasMedia)
            <flux:button
                type="button"
                variant="ghost"
                size="sm"
                icon="photo"
                wire:click="openConversationMediaViewer"
                class="shrink-0 text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
            >
                {{ __('Media') }}
            </flux:button>
        @endif
    </div>
    @error('message_delete')
        <flux:text size="sm" class="mt-2 text-red-600 dark:text-red-400">{{ $message }}</flux:text>
    @enderror
</div>
