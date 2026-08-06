<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to enforce HTTPS connections and HSTS headers for secure areas.
 * This ensures that all admin, reseller, and technician access happens over HTTPS only.
 */
class EnforceHttps
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // In production, enforce HTTPS
        if (app()->environment('production')) {
            if (!$request->secure() && !$request->header('X-Forwarded-Proto', null) === 'https') {
                // Redirect to HTTPS
                $url = $request->getSchemeAndHttpHost() . $request->getRequestUri();
                $secureUrl = str_replace('http://', 'https://', $url);
                
                return redirect()->to($secureUrl, 301);
            }
        }

        $response = $next($request);

        // Add HSTS header for production
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Add other security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Content-Security-Policy', "default-src 'self'");
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
