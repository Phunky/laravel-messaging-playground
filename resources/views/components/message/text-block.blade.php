@props([
    'vm',
])

{{--
    Body + timestamps: time pill is always floated to the end (trailing / right in LTR).
    Attachment-only rows right-align the meta strip the same way.
--}}

@if ($vm->hasBody())
    <div class="min-w-0 [display:flow-root]">
        <span class="whitespace-pre-wrap">{{ $vm->body }}</span>

        @if ($vm->sentAt !== null && $vm->sentAt !== '')
            <x-message.sent-meta
                :vm="$vm"
                class="float-end ms-2 mt-1 max-w-[calc(100%-0.5rem)] shrink-0"
            />
        @endif
    </div>
@elseif ($vm->sentAt !== null && $vm->sentAt !== '')
    <div class="mt-1.5 flex w-full min-w-0 justify-end">
        <x-message.sent-meta :vm="$vm" class="shrink-0" />
    </div>
@endif
