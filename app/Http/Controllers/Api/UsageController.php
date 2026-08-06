<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function __construct(protected UsageService $usageService)
    {
    }

    /**
     * Get current usage summary for the authenticated customer.
     */
    public function current(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        $usage = $this->usageService->getCustomerUsage($customer);

        return response()->json([
            'success' => true,
            'data' => $usage,
        ]);
    }

    /**
     * Get near real-time usage data.
     */
    public function realtime(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        $usage = $this->usageService->getNearRealtimeUsage($customer);

        return response()->json([
            'success' => true,
            'data' => $usage,
        ]);
    }

    /**
     * Get session history.
     */
    public function sessions(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);
        $limit = $request->get('limit', 20);

        $sessions = $this->usageService->getSessionHistory($customer, $limit);

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    /**
     * Get the authenticated customer from the request.
     */
    protected function getAuthenticatedCustomer(Request $request): Customer
    {
        $user = $request->user();

        // If user has a customer relationship, use that
        if ($user->customer) {
            return $user->customer;
        }

        // Otherwise, try to find customer by user ID or email
        $customer = Customer::where('email', $user->email)->first();

        if (!$customer) {
            abort(403, 'Customer not found for authenticated user.');
        }

        return $customer;
    }
}
