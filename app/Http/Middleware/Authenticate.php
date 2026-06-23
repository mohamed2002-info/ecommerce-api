<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * This is an API-only backend with no HTML `login` route, so we never
     * redirect — returning null makes the framework respond with a clean 401
     * JSON error regardless of the request's Accept header.
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
