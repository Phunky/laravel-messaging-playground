<?php

namespace Phunky\Policies;

use Phunky\LaravelMessaging\Models\Message;
use Phunky\Models\User;

class MessagePolicy
{
    public function allowRestify(?User $user): bool
    {
        return $user !== null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Message $message): bool
    {
        return $user->conversations()->whereKey($message->conversation_id)->exists();
    }

    public function store(?User $user): bool
    {
        return false;
    }

    public function update(User $user, Message $message): bool
    {
        return false;
    }

    public function delete(User $user, Message $message): bool
    {
        return false;
    }
}
