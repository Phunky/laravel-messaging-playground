<?php

namespace Phunky\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Phunky\Events\MessagingInboxUpdated;
use Phunky\LaravelMessaging\Events\AllMessagesRead;
use Phunky\LaravelMessaging\Events\MessageDeleted;
use Phunky\LaravelMessaging\Events\MessageEdited;
use Phunky\LaravelMessaging\Events\MessageSent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerMessagingInboxBroadcasts();
    }

    protected function registerMessagingInboxBroadcasts(): void
    {
        Event::listen(MessageSent::class, function (MessageSent $event): void {
            if (! config('messaging.broadcasting.enabled')) {
                return;
            }

            broadcast(new MessagingInboxUpdated((int) $event->conversation->getKey()));
        });

        Event::listen(MessageEdited::class, function (MessageEdited $event): void {
            if (! config('messaging.broadcasting.enabled')) {
                return;
            }

            $conversationId = (int) $event->message->getAttribute('conversation_id');
            if ($conversationId === 0) {
                return;
            }

            broadcast(new MessagingInboxUpdated($conversationId));
        });

        Event::listen(MessageDeleted::class, function (MessageDeleted $event): void {
            if (! config('messaging.broadcasting.enabled')) {
                return;
            }

            broadcast(new MessagingInboxUpdated((int) $event->conversation->getKey()));
        });

        Event::listen(AllMessagesRead::class, function (AllMessagesRead $event): void {
            if (! config('messaging.broadcasting.enabled')) {
                return;
            }

            broadcast(new MessagingInboxUpdated((int) $event->conversation->getKey()));
        });
    }
}
