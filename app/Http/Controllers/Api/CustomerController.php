<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $customer = $user->customer; // Assuming user has a customer relationship

        return response()->json([
            'customer' => $customer ?? null,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        // Implement profile update
        return response()->json(['message' => 'Profile updated']);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscriptions = Subscription::where('customer_id', $user->customer?->id)->get();

        return response()->json(['subscriptions' => $subscriptions]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $user = $request->user();
        $invoices = Invoice::where('customer_id', $user->customer?->id)->orderBy('due_date', 'desc')->get();

        return response()->json(['invoices' => $invoices]);
    }

    public function invoice(Request $request, Invoice $invoice): JsonResponse
    {
        return response()->json(['invoice' => $invoice]);
    }

    public function payments(Request $request): JsonResponse
    {
        $user = $request->user();
        $payments = Payment::where('customer_id', $user->customer?->id)->orderBy('paid_at', 'desc')->get();

        return response()->json(['payments' => $payments]);
    }

    public function tickets(Request $request): JsonResponse
    {
        $user = $request->user();
        $tickets = Ticket::where('customer_id', $user->customer?->id)->orderBy('created_at', 'desc')->get();

        return response()->json(['tickets' => $tickets]);
    }

    public function createTicket(Request $request): JsonResponse
    {
        // Implement ticket creation
        return response()->json(['message' => 'Ticket created']);
    }
}
