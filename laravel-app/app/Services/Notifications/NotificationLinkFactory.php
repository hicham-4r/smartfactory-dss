<?php

namespace App\Services\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

final class NotificationLinkFactory
{
    /**
     * @param array<string, mixed>|Model|int|string|null $parameters
     */
    public function route(
        string $routeName,
        array|Model|int|string|null $parameters = []
    ): string {
        if (! Route::has($routeName)) {
            return '/dashboard';
        }

        return route(
            $routeName,
            $parameters,
            false
        );
    }
}
