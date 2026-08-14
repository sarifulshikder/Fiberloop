<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectCustomerFromAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the user is authenticated as a customer and trying to access admin panel,
        // redirect them to the customer panel
        if (auth('customer')->check() && $request->is('admin', 'admin/*')) {
            return redirect()->route('filament.customer.pages.dashboard');
        }

        return $next($request);
    }
}
