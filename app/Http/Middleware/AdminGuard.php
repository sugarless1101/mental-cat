<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminGuard
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (!app()->environment('local')) {
            $secret = env('APP_ADMIN_SECRET');
            if (empty($secret) || $request->header('X-Admin-Secret') !== $secret) {
                abort(403);
            }
        }

        return $next($request);
    }
}
