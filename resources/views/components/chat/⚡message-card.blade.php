<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Phunky\Support\Chat\MessageViewModel;

/**
 * Renders a single message bubble (mine / other / group variants). The previous
 * anonymous Blade component carried `@php` blocks and inline `Carbon::parse`
 * calls; this SFC delegates everything to MessageViewModel / ChatTimestamp.
 */
new class extends Component
{
    /**
     * @var array<string, mixed>
     */
    #[Locked]
    public array $message = [];

    #[Locked]
    public ?int $conversationId = null;

    #[Locked]
    public bool $isGroup = false;

    /**
     * @param  array<string, mixed>  $message
     */
    public function mount(array $message, ?int $conversationId = null, bool $isGroup = false): void
    {
        $this->message = $message;
        $this->conversationId = $conversationId;
        $this->isGroup = $isGroup;
    }

    #[Computed]
    public function viewModel(): MessageViewModel
    {
        return MessageViewModel::fromArray($this->message);
    }

    public function startEdit(): void
    {
        $id = $this->viewModel->id;
        if ($id > 0) {
            $this->dispatch('message-pane-start-edit', messageId: $id);
        }
    }

    public function requestDelete(): void
    {
        $id = $this->viewModel->id;
        if ($id > 0) {
            $this->dispatch('message-pane-request-delete', messageId: $id);
        }
    }
};
?>

<div
    @class([
        'flex last:mb-6',
        'justify-end' => $this->viewModel->isMe,
        'justify-start' => ! $this->viewModel->isMe,
    ])
>
    @if (! $this->viewModel->isMe && $isGroup)
        <div class="flex max-w-[80%] flex-row gap-2">
            <flux:avatar
                :name="$this->viewModel->senderName"
                color="auto"
                :color:seed="$this->viewModel->senderId"
                size="xs"
            />
            <div class="min-w-0 flex-1">
                @include('components.chat.message-card._bubble', ['vm' => $this->viewModel, 'isGroup' => $isGroup])
            </div>
        </div>
    @else
        @include('components.chat.message-card._bubble', ['vm' => $this->viewModel, 'isGroup' => $isGroup])
    @endif
</div>
