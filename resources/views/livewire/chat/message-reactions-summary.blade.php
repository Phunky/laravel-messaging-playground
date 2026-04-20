<?php

use Livewire\Component;
use Phunky\Livewire\Concerns\HandlesMessageReactions;

new class extends Component
{
    use HandlesMessageReactions;
};
?>

<div wire:key="reactions-summary-{{ $messageId }}" class="relative w-full">
    @if ($this->reactionState['summary']->isNotEmpty())
        <div
            @class([
                'mt-1 flex flex-wrap items-center gap-1',
                'justify-end pe-2' => $messageAlignment === 'mine',
                'justify-start ps-2' => $messageAlignment !== 'mine',
            ])
        >
            @foreach ($this->reactionState['summary'] as $row)
                <button
                    type="button"
                    wire:click="toggle({{ \Illuminate\Support\Js::from($row['reaction']) }})"
                    title="{{ $row['title'] }}"
                    class="inline-flex items-center gap-1 rounded-full border bg-white px-2 py-0.5 text-xs shadow-sm transition-colors hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-800 {{ $this->reactionState['my_reaction'] === $row['reaction'] ? 'border-zinc-900 dark:border-zinc-100' : 'border-zinc-200 dark:border-zinc-700' }}"
                >
                    @if ($this->isFluxIconKey($row['reaction']))
                        <flux:icon name="{{ $row['reaction'] }}" variant="micro" class="size-3.5" />
                    @else
                        <span>{{ $row['reaction'] }}</span>
                    @endif
                    <span class="tabular-nums text-zinc-600 dark:text-zinc-400">{{ $row['count'] }}</span>
                </button>
            @endforeach
        </div>
    @endif
</div>
