<?php

namespace Phunky\Extensions;

use Illuminate\Contracts\Foundation\Application;
use Phunky\LaravelMessaging\Contracts\MessagingExtension;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessagingGroups\GroupsExtension;

/**
 * Scaffold for app-level laravel-messaging extensions.
 *
 * Register this class in config/messaging.php under the `extensions` array.
 *
 * Typical uses:
 * - {@see Application::singleton()} bindings in {@see register()}
 * - {@see $app['events']->listen()} for package events in {@see boot()}
 * - {@see Message::macro()} for fluent APIs
 * - {@see $app->afterResolving('migrator', ...)} to add migration paths (see {@see GroupsExtension})
 */
class ExampleExtension implements MessagingExtension
{
    public function register(Application $app): void
    {
        // Example: $app->singleton(SomeService::class, fn () => new SomeService(...));
    }

    public function boot(Application $app): void
    {
        // Example: $app['events']->listen(\Phunky\LaravelMessaging\Events\MessageSent::class, ...);
    }
}
