<?php

use Illuminate\Support\Facades\Broadcast;
use Phunky\Models\User;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('messaging.conversation.{conversationId}', function (?User $user, int $conversationId) {
    if (! $user) {
        return null;
    }

    if (! $user->conversations()->whereKey($conversationId)->exists()) {
        return null;
    }

    return [
        'id' => $user->getKey(),
        'name' => $user->name,
    ];
});
