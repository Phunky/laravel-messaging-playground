<?php

namespace Phunky\Policies;

use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\Models\User;

class ConversationPolicy
{
    public function allowRestify(?User $user): bool
    {
        return $user !== null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $user->conversations()->whereKey($conversation->getKey())->exists();
    }

    public function store(?User $user): bool
    {
        return false;
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return false;
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return false;
    }
}
