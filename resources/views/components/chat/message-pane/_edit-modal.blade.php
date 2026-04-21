<flux:modal wire:model.self="editModalOpen" wire:cancel="cancelEdit" class="md:w-lg">
    <div class="space-y-4">
        <flux:heading size="lg">{{ __('Edit message') }}</flux:heading>
        <flux:textarea wire:model="editMessageBody" rows="6" />
        @error('editMessageBody')
            <flux:text size="sm" class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
        <div class="flex gap-2">
            <flux:spacer />
            <flux:button type="button" variant="ghost" wire:click="cancelEdit">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="button" variant="primary" wire:click="saveEdit">
                {{ __('Save') }}
            </flux:button>
        </div>
    </div>
</flux:modal>
