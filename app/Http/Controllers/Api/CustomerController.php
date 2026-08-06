<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\CustomerResource;
use App\Http\Resources\Api\InvoiceResource;
use App\Http\Resources\Api\PaymentResource;
use App\Http\Resources\Api\SubscriptionResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Get the authenticated customer's profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);
        
        return response()->json([
            'success' => true,
            'data' => new CustomerResource($customer),
        ]);
    }

    /**
     * Update the customer's profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'alternate_phone' => 'nullable|string|max:20',
            'email' => 'sometimes|email|max:255',
            'service_address' => 'sometimes|string|max:500',
            'billing_address' => 'sometimes|string|max:500',
            'area' => 'sometimes|string|max:100',
            'zone' => 'sometimes|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = $this->getAuthenticatedCustomer($request);
        
        $customer->update($request->only([
            'first_name',
            'last_name',
            'phone',
            'alternate_phone',
            'email',
            'service_address',
            'billing_address',
            'area',
            'zone',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => new CustomerResource($customer->fresh()),
        ]);
    }

    /**
     * Get customer's active subscription.
     */
    public function activeSubscription(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);
        
        $subscription = Subscription::where('customer_id', $customer->id)
            ->active()
            ->with(['package', 'reseller', 'networkDevice', 'olt', 'onu'])
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No active subscription found',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    /**
     * Get all customer subscriptions.
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);
        
        $subscriptions = Subscription::where('customer_id', $customer->id)
            ->with(['package', 'reseller'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => SubscriptionResource::collection($subscriptions),
        ]);
    }

    /**
     * Get all customer invoices.
     */
    public function invoices(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);
        
        $invoices = Invoice::where('customer_id', $customer->id)
            ->with(['subscription.package', 'customer'])
            ->orderBy('due_date', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => InvoiceResource::collection($invoices),
        ]);
    }

    /**
     * Get a specific invoice.
     */
    public function invoice(Request $request, Invoice $invoice): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);
        
        // Authorize: customer can only view their own invoices
        if ($invoice->customer_id !== $customer->id) {
            abort(403, 'Unauthorized access to this invoice.');
        }
        
        $invoice->load(['items', 'subscription.package', 'payments']);

        return response()->json([
            'success' => true,
            'data' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * Get all customer payments.
     */
    public function payments(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);
        
        $payments = Payment::where('customer_id', $customer->id)
            ->with(['invoice', 'subscription'])
            ->orderBy('paid_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => PaymentResource::collection($payments),
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
