<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TicketApiController extends Controller
{
    public function index(Request $request)
    {
        $customerId = Auth::user()->customer_id ?? Auth::id();

        $tickets = Ticket::where('customer_id', $customerId)
            ->latest()
            ->paginate(15);

        return TicketResource::collection($tickets);
    }

    public function show(Ticket $ticket)
    {
        $customerId = Auth::user()->customer_id ?? Auth::id();

        if ($ticket->customer_id !== $customerId) {
            abort(403, 'Unauthorized access to this ticket.');
        }

        $ticket->load(['comments' => function ($query) {
            $query->where('is_internal', false)->latest();
        }]);

        return new TicketResource($ticket);
    }

    public function store(Request $request, TicketService $ticketService)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string',
        ]);

        $customerId = Auth::user()->customer_id ?? Auth::id();

        $data = array_merge($validated, [
            'customer_id' => $customerId,
            'ticket_number' => 'TKT-' . strtoupper(Str::random(8)),
            'uuid' => (string) Str::uuid(),
            'source' => 'customer_portal',
            'status' => 'open',
            'created_by' => Auth::id() ?? 1,
        ]);

        $ticket = $ticketService->createTicket($data);

        return new TicketResource($ticket);
    }
}
