<?php

namespace Phunky\Providers;

use Binaryk\LaravelRestify\Restify;
use Binaryk\LaravelRestify\RestifyApplicationServiceProvider;

class RestifyServiceProvider extends RestifyApplicationServiceProvider
{
    protected function authorization(): void
    {
        Restify::auth(function ($request) {
            return $request->user() !== null;
        });
    }

    protected function repositories(): void
    {
        Restify::repositoriesFrom(app_path('Restify'), 'Phunky\\Restify\\');
    }

    protected function gate(): void
    {
        // Intentionally empty: Restify::auth handles API access for authenticated users.
    }
}
