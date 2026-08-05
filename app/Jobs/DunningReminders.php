<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\Billing\LateFeeService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Job to send dunning (payment reminder) notifications.
 * Sends reminders on schedule: day 1, day 3, day 7 overdue.
 * Fires events that Phase 11 (Notifications) will consume.
 */
class DunningReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $daysOverdue;

    public function __construct(int $daysOverdue = 1)
    {
        $this->daysOverdue = $daysOverdue;
    }

    public function handle(LateFeeService $lateFeeService): void
    {
        Log::info("Starting dunning reminders job for day {$this->daysOverdue}");
        
        $gracePeriodDays = 5; // Default grace period
        $dueDateThreshold = now()->subDays($gracePeriodDays + $this->daysOverdue);
        
        $overdueInvoices = Invoice::query()
            ->whereIn('status', ['sent', 'overdue', 'partial'])
            ->where('due_date', '<=', $dueDateThreshold->toDateString())
            ->where('outstanding_amount', '>', 0)
            ->whereDoesntHave('notifications', function ($query) use ($daysOverdue) {
                $query->where('type', 'dunning_' . $daysOverdue);
            })
            ->with(['customer'])
            ->get();

        $processed = 0;
        
        foreach ($overdueInvoices as $invoice) {
            if ($this->shouldSendReminder($invoice, $gracePeriodDays)) {
                $this->fireDunningEvent($invoice);
                $processed++;
            }
        }
        
        Log::info("Dunning reminders completed", [
            'day' => $this->daysOverdue,
            'invoices_processed' => $processed,
        ]);
    }

    /**
     * Check if we should send a reminder for this invoice.
     */
    protected function shouldSendReminder(Invoice $invoice, int $gracePeriodDays): bool
    {
        $dueDate = Carbon::parse($invoice->due_date);
        $gracePeriodEnd = $dueDate->copy()->addDays($gracePeriodDays);
        
        // Invoice must be past grace period
        if (now()->isBefore($gracePeriodEnd)) {
            return false;
        }
        
        $daysOverdue = now()->diffInDays($gracePeriodEnd);
        
        // Check if this matches our reminder schedule
        return match ($this->daysOverdue) {
            1 => $daysOverdue >= 1 && $daysOverdue < 3,
            3 => $daysOverdue >= 3 && $daysOverdue < 7,
            7 => $daysOverdue >= 7,
            default => false,
        };
    }

    /**
     * Fire dunning event for Phase 11 to consume.
     */
    protected function fireDunningEvent(Invoice $invoice): void
    {
        $daysOverdue = $this->daysOverdue;
        
        // In a real implementation, this would fire a specific DunningReminder event
        // For now, we'll log it and Phase 11 can extend this
        Log::info("Dunning reminder fired", [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'customer_id' => $invoice->customer_id,
            'days_overdue' => $daysOverdue,
            'outstanding_amount' => $invoice->outstanding_amount,
        ]);
        
        // TODO: Fire actual event when Phase 11 is implemented
        // event(new \App\Events\Notifications\DunningReminder(
        //     $invoice->customer,
        //     $invoice,
        //     $daysOverdue
        // ));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Dunning reminders job failed", [
            'day' => $this->daysOverdue,
            'error' => $exception->getMessage(),
        ]);
    }
}