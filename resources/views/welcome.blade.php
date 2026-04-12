<x-layouts.app :title="config('app.name').' — Laravel Messaging playground'">
    <div class="mx-auto flex max-w-3xl flex-col gap-6 px-6 py-16">
        <flux:heading size="xl">Laravel Messaging test app</flux:heading>
        <flux:text class="text-zinc-600 dark:text-zinc-400">
            This application is wired for <strong>phunky/laravel-messaging</strong>, the <strong>Groups</strong> extension,
            and <strong>Flux</strong> UI. Run <flux:badge color="zinc">php artisan migrate --seed</flux:badge> to load demo users and conversations.
        </flux:text>
        <flux:separator />
        <div class="flex flex-wrap gap-3">
            <flux:button href="https://github.com/Phunky/laravel-messaging" tag="a" target="_blank" variant="primary">
                Package repo
            </flux:button>
            <flux:button href="https://fluxui.dev/docs/installation" tag="a" target="_blank" variant="ghost">
                Flux docs
            </flux:button>
        </div>
    </div>
</x-layouts.app>
