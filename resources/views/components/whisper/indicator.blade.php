@props([
    'users' => [],
    'variant' => 'typing',
    'scope' => 'pane',
])

{{--
    Pure presentational whisper indicator. All label/color logic is encapsulated
    in Phunky\Support\Chat\WhisperLabel and @class bindings; no @php blocks.
--}}

<span
    @class([
        'flex min-w-0 items-center gap-1.5',
        'text-sm italic text-red-600 dark:text-red-400' => $variant === 'recording' && $scope === 'inbox',
        'text-xs italic text-zinc-500 dark:text-zinc-400' => $variant === 'recording' && $scope !== 'inbox',
        'text-sm italic text-emerald-600 dark:text-emerald-400' => $variant !== 'recording' && $scope === 'inbox',
        'text-xs italic text-zinc-500 dark:text-zinc-400' => $variant !== 'recording' && $scope !== 'inbox',
    ])
    aria-live="polite"
>
    <x-whisper.leading :users="$users" :variant="$variant" :scope="$scope" layout="indicator" />
</span>
