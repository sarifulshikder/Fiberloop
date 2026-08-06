<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Incident;
use App\Models\Ticket;

class TicketService
{
    public function createTicket(array $data): Ticket
    {
        // Check if customer area has an active incident to correlate (Phase 10 Task 2)
        if (empty($data['incident_id']) && !empty($data['customer_id'])) {
            $category = $data['category'] ?? 'technical';

            if (in_array($category, ['technical', 'no_internet'])) {
                $customer = Customer::find($data['customer_id']);
                if ($customer && $customer->area) {
                    $incident = Incident::where('area_zone', $customer->area)
                        ->where('status', '!=', 'resolved') // Assuming active if not resolved
                        ->first();

                    if ($incident) {
                        $data['incident_id'] = $incident->id;
                    }
                }
            }
        }

        return Ticket::create($data);
    }
}
