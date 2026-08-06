<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PackageChangeRequestResource;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageChangeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PackageChangeRequestController extends Controller
{
    /**
     * Get all package change requests for the authenticated customer.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        $requests = PackageChangeRequest::where('customer_id', $customer->id)
            ->with(['currentPackage', 'requestedPackage', 'subscription'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => PackageChangeRequestResource::collection($requests),
        ]);
    }

    /**
     * Get a specific package change request.
     */
    public function show(Request $request, PackageChangeRequest $packageChangeRequest): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        // Authorize: customer can only view their own requests
        if ($packageChangeRequest->customer_id !== $customer->id) {
            abort(403, 'Unauthorized access to this request.');
        }

        $packageChangeRequest->load(['currentPackage', 'requestedPackage', 'subscription', 'approvedBy']);

        return response()->json([
            'success' => true,
            'data' => new PackageChangeRequestResource($packageChangeRequest),
        ]);
    }

    /**
     * Create a new package change request.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
            'subscription_id' => 'nullable|exists:subscriptions,id',
            'change_type' => 'required|in:upgrade,downgrade,change',
            'effective_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = $this->getAuthenticatedCustomer($request);

        // Find active subscription if not provided
        $subscriptionId = $request->subscription_id ?? $customer->subscriptions()->active()->first()?->id;

        if (!$subscriptionId) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found for this customer.',
            ], 400);
        }

        $subscription = $customer->subscriptions()->findOrFail($subscriptionId);
        $package = Package::findOrFail($request->package_id);

        // Create the request
        $changeRequest = PackageChangeRequest::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'current_package_id' => $subscription->package_id,
            'requested_package_id' => $package->id,
            'change_type' => $request->change_type,
            'status' => 'pending',
            'effective_date' => $request->effective_date ?? now()->addDay(),
            'notes' => $request->notes,
            'created_by' => Auth::id(),
        ]);

        $changeRequest->load(['currentPackage', 'requestedPackage', 'subscription']);

        return response()->json([
            'success' => true,
            'message' => 'Package change request created successfully',
            'data' => new PackageChangeRequestResource($changeRequest),
        ], 201);
    }

    /**
     * Cancel a package change request.
     */
    public function cancel(Request $request, PackageChangeRequest $packageChangeRequest): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        // Authorize: customer can only cancel their own requests
        if ($packageChangeRequest->customer_id !== $customer->id) {
            abort(403, 'Unauthorized access to this request.');
        }

        // Can only cancel pending requests
        if ($packageChangeRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be cancelled.',
            ], 400);
        }

        $packageChangeRequest->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
            'cancellation_reason' => $request->get('reason', 'Customer request'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Package change request cancelled',
            'data' => new PackageChangeRequestResource($packageChangeRequest->fresh()),
        ]);
    }

    /**
     * Get available packages for upgrade/downgrade.
     */
    public function availablePackages(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        $subscription = $customer->subscriptions()->active()->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found.',
            ], 400);
        }

        $currentPackage = $subscription->package;

        // Get all active packages
        $packages = Package::active()
            ->orderBy('price')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'current_package' => new \App\Http\Resources\Api\PackageResource($currentPackage),
                'available_packages' => \App\Http\Resources\Api\PackageResource::collection($packages),
            ],
        ]);
    }

    /**
     * Get the authenticated customer from the request.
     */
    protected function getAuthenticatedCustomer(Request $request): Customer
    {
        $user = $request->user();

        if ($user->customer) {
            return $user->customer;
        }

        $customer = Customer::where('email', $user->email)->first();

        if (!$customer) {
            abort(403, 'Customer not found for authenticated user.');
        }

        return $customer;
    }
}
