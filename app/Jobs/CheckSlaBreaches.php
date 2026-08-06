<?php

namespace App\Jobs;

use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckSlaBreaches implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $breachedTickets = Ticket::overdue()->get();

        foreach ($breachedTickets as $ticket) {
            Log::warning("SLA Breach: Ticket {$ticket->ticket_number} is overdue.");

            $tags = $ticket->tags ?? [];
            if (!in_array('sla_breached', $tags)) {
                $tags[] = 'sla_breached';
                $ticket->tags = $tags;
                $ticket->save();
            }
        }
    }
}
