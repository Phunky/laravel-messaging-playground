{{-- Empty state: keep @island out of @if (Livewire islands restriction). --}}
<div
    class="@if ($conversationId !== null) hidden @endif flex min-h-0 flex-1 items-center justify-center p-8"
>
    <flux:text class="text-center text-zinc-500">{{ __('Select a conversation to start messaging.') }}</flux:text>
</div>
