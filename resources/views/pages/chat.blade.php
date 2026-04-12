<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Phunky\Models\User;

new
#[Layout('layouts::app')]
#[Title('Messages')]
class extends Component
{
    /**
     * Below the `lg` breakpoint: which full-screen pane is visible (`list` = inbox, `messages` = thread).
     */
    public string $mobileStack = 'list';

    public ?int $selectedConversationId = null;

    /**
     * Open a conversation from the inbox. Lives on the page component so list + message pane + mobile
     * stack update in a single Livewire round trip (sibling-only events are unreliable on first click).
     */
    public function selectConversation(int $conversationId): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        if (! $user->conversations()->whereKey($conversationId)->exists()) {
            return;
        }

        $this->selectedConversationId = $conversationId;
        $this->mobileStack = 'messages';
    }

    #[On('chat-mobile-back')]
    public function onChatMobileBack(): void
    {
        $this->mobileStack = 'list';
    }
};
?>

<div class="flex h-screen max-h-screen w-full min-h-0 flex-col overflow-hidden">
    <div class="flex min-h-0 min-w-0 flex-1 overflow-hidden">
        <aside
            @class([
                'flex h-full min-h-0 w-full max-w-none shrink-0 flex-col overflow-hidden border-r border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900',
                'max-lg:hidden' => $mobileStack === 'messages',
                'lg:w-80 lg:max-w-sm lg:shrink-0',
            ])
        >
            <livewire:chat.conversation-list :selected-conversation-id="$selectedConversationId" />

            <div class="p-2 border-t border-zinc-200 dark:border-zinc-800">
                <flux:dropdown>
                    <flux:profile name="{{ auth()->user()->name }}" />

                    <flux:navmenu class="max-w-[12rem]">
                        <div class="px-2 py-1.5">
                            <flux:text size="sm">Signed in as</flux:text>
                            <flux:heading class="mt-1! truncate">{{ auth()->user()->email }}</flux:heading>
                        </div>

                        <flux:navmenu.separator />

                        <flux:navmenu.item href="{{ route('settings.profile') }}" icon="user" class="text-zinc-800 dark:text-white">{{ __('Profile') }}</flux:navmenu.item>

                        <flux:navmenu.separator />

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <flux:navmenu.item type="submit" icon="arrow-right-start-on-rectangle" class="text-zinc-800 dark:text-white">{{ __('Log out') }}</flux:navmenu.item>
                        </form>
                    </flux:navmenu>
                </flux:dropdown>
            </div>
        </aside>

        <main
            @class([
                'flex h-full min-h-0 min-w-0 flex-1 flex-col items-center overflow-hidden bg-zinc-50 dark:bg-zinc-950',
                'max-lg:hidden' => $mobileStack === 'list',
                'lg:flex',
            ])
        >
            <livewire:chat.message-pane
                wire:key="message-pane-{{ $selectedConversationId ?? 'none' }}"
                :conversation-id="$selectedConversationId"
            />
        </main>
    </div>
</div>
