<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogPermissionDenied
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (AuthorizationException $e) {
            // Log the permission denied event
            $user = $request->user();

            activity()
                ->by($user)
                ->withProperties([
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'ability' => $e->getAbility(),
                    'arguments' => $e->getArguments(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log('Permission denied');

            throw $e;
        }
    }
}
