<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTwoFactor
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Admin-tier roles that require 2FA
        $adminRoles = ['super_admin', 'admin'];

        if ($user->hasAnyRole($adminRoles) && !$user->hasTwoFactorEnabled()) {
            // Log the 2FA enforcement attempt
            activity()
                ->by($user)
                ->withProperties([
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'roles' => $user->getRoleNames()->toArray(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log('2FA enforcement: admin role without 2FA enabled');

            // Redirect to 2FA setup page
            // Using Filament's built-in 2FA setup route
            return redirect()->route('filament.admin.pages.setup-two-factor-authentication');
        }

        return $next($request);
    }
}
