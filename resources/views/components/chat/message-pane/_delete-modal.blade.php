<flux:modal
    wire:model.self="deleteModalOpen"
    wire:cancel="cancelDeleteMessage"
    class="min-w-[22rem]"
>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Delete message?') }}</flux:heading>
            <flux:text class="mt-2">{{ __('This message will be removed from the conversation.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:spacer />
            <flux:button type="button" variant="ghost" wire:click="cancelDeleteMessage">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="button" variant="danger" wire:click="confirmDeleteMessage">
                {{ __('Delete') }}
            </flux:button>
        </div>
    </div>
</flux:modal>
