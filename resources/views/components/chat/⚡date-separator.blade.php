<?php

use Livewire\Component;

new class extends Component
{
    public ?string $sentAt = null;
};
?>

<div class="sticky top-0 z-10 flex items-center">
    <flux:spacer />
    <flux:badge size="sm" variant="solid" rounded class="min-w-[100px] justify-around">
        <x-message.timestamp :iso="$sentAt" preset="date_separator" />
    </flux:badge>
    <flux:spacer />
</div>
