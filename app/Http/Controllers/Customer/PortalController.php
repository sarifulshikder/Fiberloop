<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Services\UsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalController extends Controller
{
    public function __construct(protected UsageService $usageService)
    {
    }

    /**
     * Customer Dashboard
     */
    public function dashboard(Request $request)
    {
        $customer = $this->getCustomer();
        
        $subscription = Subscription::where('customer_id', $customer->id)
            ->active()
            ->with(['package'])
            ->first();

        $invoices = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['draft', 'sent', 'overdue', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->orderBy('due_date', 'asc')
            ->limit(5)
            ->get();

        $recentPayments = Payment::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get();

        $openTickets = Ticket::where('customer_id', $customer->id)
            ->where('status', 'open')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $usage = $this->usageService->getCustomerUsage($customer);

        return view('customer.dashboard', [
            'customer' => $customer,
            'subscription' => $subscription,
            'invoices' => $invoices,
            'recentPayments' => $recentPayments,
            'openTickets' => $openTickets,
            'usage' => $usage,
        ]);
    }

    /**
     * Get the authenticated customer.
     */
    protected function getCustomer(): Customer
    {
        $user = Auth::user();
        
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
