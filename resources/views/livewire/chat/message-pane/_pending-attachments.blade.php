@if ($pendingFiles !== [] && ! $suppressPendingAttachmentPreview)
    <div class="mb-2 flex flex-wrap gap-2">
        @foreach ($this->pendingAttachments as $index => $attachment)
            <div
                @class([
                    'relative overflow-hidden',
                    'shrink-0' => ! $attachment->isAudio(),
                    'h-16 w-16' => ! $attachment->isAudio(),
                    'min-h-16 w-full max-w-xs px-2 py-1.5' => $attachment->isAudio(),
                    'rounded-md border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800' => ! $attachment->isAudio() && ! ($attachment->canPreviewVideo() && $this->attachmentKind === 'video_note'),
                ])
                wire:key="pending-file-{{ $index }}"
            >
                @if ($attachment->canPreviewImage())
                    <img
                        src="{{ $attachment->url() }}"
                        alt=""
                        class="h-full w-full object-cover"
                    />
                @elseif ($attachment->canPreviewVideo() && $this->attachmentKind === 'video_note')
                    <x-chat.video-note-circle-shell variant="mine" size="compact">
                        <video
                            src="{{ $attachment->url() }}"
                            crossorigin="anonymous"
                            muted
                            playsinline
                            preload="{{ $attachment->videoPosterPreload() }}"
                            data-mime-type="{{ $attachment->videoPosterDataMimeType() }}"
                            class="chat-video-poster size-full object-cover"
                        ></video>
                    </x-chat.video-note-circle-shell>
                @elseif ($attachment->canPreviewVideo())
                    <video
                        src="{{ $attachment->url() }}"
                        crossorigin="anonymous"
                        muted
                        playsinline
                        preload="{{ $attachment->videoPosterPreload() }}"
                        data-mime-type="{{ $attachment->videoPosterDataMimeType() }}"
                        class="chat-video-poster h-full w-full object-cover"
                    ></video>
                @elseif ($attachment->isImageOrVideoWithoutPreview())
                    <div class="flex h-full w-full flex-col items-center justify-center gap-0.5 p-1 text-center">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="size-6 shrink-0 text-zinc-500 dark:text-zinc-400"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"
                            />
                        </svg>
                        <span class="line-clamp-2 w-full break-all text-[0.65rem] leading-tight text-zinc-600 dark:text-zinc-300">
                            {{ $attachment->filename() }}
                        </span>
                    </div>
                @elseif ($attachment->canPreviewAudio())
                    <audio
                        src="{{ $attachment->url() }}"
                        controls
                        preload="metadata"
                        controlslist="nodownload noplaybackrate"
                        class="chat-native-audio h-10 min-w-[220px] w-full"
                    ></audio>
                @elseif ($attachment->isAudio())
                    <div class="flex h-full w-full flex-col items-center justify-center gap-0.5 p-1 text-center">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="size-6 shrink-0 text-zinc-500 dark:text-zinc-400"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.829.112-1.632.338-2.396.234-.847.958-1.354 1.938-1.354H6.75Z"
                            />
                        </svg>
                        <span class="line-clamp-2 w-full break-all text-[0.65rem] leading-tight text-zinc-600 dark:text-zinc-300">
                            {{ $attachment->filename() }}
                        </span>
                    </div>
                @else
                    <div class="flex h-full w-full flex-col items-center justify-center gap-0.5 p-1 text-center">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="size-6 shrink-0 text-zinc-500 dark:text-zinc-400"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                            />
                        </svg>
                        <span class="line-clamp-2 w-full break-all text-[0.65rem] leading-tight text-zinc-600 dark:text-zinc-300">
                            {{ $attachment->filename() }}
                        </span>
                    </div>
                @endif
                <button
                    type="button"
                    wire:click="removePendingFile({{ $index }})"
                    class="absolute end-0.5 top-0.5 flex size-5 items-center justify-center rounded-full bg-zinc-900/75 text-xs text-white hover:bg-zinc-900"
                    title="{{ __('Remove') }}"
                >
                    ×
                </button>
            </div>
        @endforeach
    </div>
@endif
