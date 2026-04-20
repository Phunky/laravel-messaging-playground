<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Phunky\Support\Chat\ChatTimestamp;

new class extends Component
{
    public ?string $sentAt = null;

    #[Computed]
    public function label(): string
    {
        return ChatTimestamp::dateSeparator($this->sentAt);
    }
};
?>

<div class="sticky top-0 flex items-center z-10">
    <flux:spacer />
    <flux:badge size="sm" variant="solid" rounded class="min-w-[100px] justify-around">{{ $this->label }}</flux:badge>
    <flux:spacer />
</div>
