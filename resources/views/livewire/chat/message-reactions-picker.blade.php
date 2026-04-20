<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Phunky\Livewire\Concerns\HandlesMessageReactions;

new class extends Component
{
    use HandlesMessageReactions;

    public bool $pickerOpen = false;

    #[On('open-message-reaction-picker')]
    public function onOpenMessageReactionPicker(int $messageId): void
    {
        if ($messageId !== $this->messageId) {
            return;
        }

        $this->pickerOpen = true;
    }

    public function pickerPositionClasses(): string
    {
        return $this->messageAlignment === 'mine'
            ? 'right-full top-1/2 -translate-y-1/2 mr-2'
            : 'left-full top-1/2 -translate-y-1/2 ml-2';
    }
};
?>

<div wire:key="reactions-picker-{{ $messageId }}" class="w-full">
    <div
        @class([
            'pointer-events-auto absolute z-20 transition-opacity',
            $this->pickerPositionClasses(),
            'opacity-100' => $this->pickerOpen,
            'opacity-0 md:group-hover:opacity-100 md:group-focus-within:opacity-100' => ! $this->pickerOpen,
        ])
    >
        <flux:dropdown wire:model="pickerOpen" position="bottom" align="{{ $messageAlignment === 'mine' ? 'end' : 'start' }}">
            <flux:button
                variant="ghost"
                size="xs"
                icon="face-smile"
                icon:variant="outline"
                class="!size-7 !rounded-full border border-zinc-400/60 !bg-transparent !shadow-none dark:border-zinc-500/60"
            />

            <flux:popover class="flex flex-col gap-2 p-2">
                <div class="flex items-center gap-1">
                    @foreach ($emojiPicker as $emoji)
                        <button
                            type="button"
                            wire:click="toggle({{ \Illuminate\Support\Js::from($emoji) }})"
                            class="rounded px-1.5 py-1 text-base hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $this->reactionState['my_reaction'] === $emoji ? 'bg-zinc-100 ring-1 ring-zinc-400 dark:bg-zinc-700 dark:ring-zinc-500' : '' }}"
                        >
                            {{ $emoji }}
                        </button>
                    @endforeach
                </div>

                <flux:separator variant="subtle" />

                <div class="flex items-center gap-1">
                    @foreach ($iconPicker as $item)
                        <button
                            type="button"
                            wire:click="toggle({{ \Illuminate\Support\Js::from($item['key']) }})"
                            class="rounded p-1 hover:bg-zinc-100 dark:hover:bg-zinc-700 {{ $this->reactionState['my_reaction'] === $item['key'] ? 'bg-zinc-100 ring-1 ring-zinc-400 dark:bg-zinc-700 dark:ring-zinc-500' : '' }}"
                        >
                            <flux:icon name="{{ $item['icon'] }}" variant="mini" class="size-5" />
                        </button>
                    @endforeach
                </div>
            </flux:popover>
        </flux:dropdown>
    </div>
</div>
