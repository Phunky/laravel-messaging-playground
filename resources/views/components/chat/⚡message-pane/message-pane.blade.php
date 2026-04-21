<div class="flex h-full min-h-0 min-w-0 w-full flex-1 flex-col overflow-hidden">
    @include('components.chat.message-pane._empty')

    <div
        class="@if ($conversationId === null) hidden @endif flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
    >
        @include('components.chat.message-pane._header')

        <div class="flex min-h-0 w-full flex-1 flex-col overflow-hidden">
            <div
                id="chat-scroll-area"
                class="flex min-h-0 flex-1 basis-0 flex-col overflow-y-auto overscroll-contain py-3"
                x-data="{ ready: false }"
                x-init="$nextTick(() => { ready = true })"
            >
                @foreach ($warmConversationIds as $cid)
                    <div
                        class="@if ((int) $conversationId !== (int) $cid) hidden @endif min-h-0"
                        wire:key="warm-wrap-{{ $cid }}"
                    >
                        <livewire:chat.message-thread
                            :conversation-id="$cid"
                            :is-active="(int) $conversationId === (int) $cid"
                            wire:key="message-thread-{{ $cid }}"
                        />
                    </div>
                @endforeach
            </div>

            @include('components.chat.message-pane._edit-modal')
            @include('components.chat.message-pane._delete-modal')

            @if ($mediaViewerOpen)
                <x-message.conversation-media-viewer :items="$mediaViewerItems" :index="$mediaViewerIndex" />
            @endif

            @include('components.chat.message-pane._composer')
        </div>
    </div>
</div>