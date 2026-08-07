<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to restrict access to KYC document endpoints to authorized roles only.
 * This ensures that sensitive KYC data (NID numbers, photos) are only accessible
 * by users with appropriate permissions.
 */
class RestrictKycAccess
{
    /**
     * Roles that are authorized to access KYC data.
     */
    protected array $authorizedRoles = [
        'super_admin',
        'admin',
        'billing_agent',
        'support_agent',
        'noc_engineer',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If user is not authenticated, deny access
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to KYC data',
                'error_code' => 'KYC_ACCESS_DENIED',
            ], 403);
        }

        $user = $request->user();

        // Check if user has any of the authorized roles
        foreach ($this->authorizedRoles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        // Check if user is a reseller trying to access their own customer's KYC
        if ($user->hasRole('reseller')) {
            $customerId = $request->route('customer')?->id ?? $request->input('customer_id');

            if ($customerId) {
                $customer = \App\Models\Customer::find($customerId);

                // Resellers can only access KYC of their own customers
                if ($customer && $customer->created_by === $user->id) {
                    return $next($request);
                }
            }
        }

        // Log unauthorized access attempt
        \Illuminate\Support\Facades\Log::warning('Unauthorized KYC data access attempt', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_roles' => $user->getRoleNames()->toArray(),
            'request_path' => $request->path(),
            'request_method' => $request->method(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to access KYC data',
            'error_code' => 'KYC_ACCESS_DENIED',
        ], 403);
    }
}
