<?php

namespace Phunky\Restify;

use Binaryk\LaravelRestify\Attributes\Model as RestifyModel;
use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Repositories\Repository;
use Illuminate\Validation\Rule;
use Phunky\Models\User;

#[RestifyModel(User::class)]
final class UserRepository extends Repository
{
    public static function indexQuery(RestifyRequest $request, $query)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $query->whereRaw('0=1');
        }

        return $query->whereKey($user->getKey());
    }

    public function fields(RestifyRequest $request): array
    {
        return [
            field('name')->rules(['required', 'string', 'max:255']),
            field('email')->rules([
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($request->user()?->getKey()),
            ]),
        ];
    }
}
