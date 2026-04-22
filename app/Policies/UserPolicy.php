<?php

namespace Phunky\Policies;

use Phunky\Models\User;

class UserPolicy
{
    public function allowRestify(?User $user): bool
    {
        return $user !== null;
    }

    public function viewAny(?User $user): bool
    {
        return $user !== null;
    }

    public function view(?User $user, User $model): bool
    {
        return $user !== null && (int) $user->getKey() === (int) $model->getKey();
    }

    public function update(User $user, User $model): bool
    {
        return (int) $user->getKey() === (int) $model->getKey();
    }

    public function store(?User $user): bool
    {
        return false;
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }
}
