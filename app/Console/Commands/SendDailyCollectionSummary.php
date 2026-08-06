<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\Invoice;
use App\Models\User;
use App\Mail\DailyCollectionSummaryMail;
use Carbon\Carbon;

class SendDailyCollectionSummary extends Command
{
    protected $signature = 'reports:daily-collection-summary';

    protected $description = 'Send a daily collection summary email to admins';

    public function handle(): int
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // Aggregate paid invoices today
        $collected = Invoice::where('status', 'paid')
            ->whereDate('updated_at', $today)
            ->sum('total') / 100;

        // Outstanding unpaid
        $outstanding = Invoice::where('status', 'unpaid')
            ->sum('total') / 100;

        $stats = [
            'date' => $today,
            'collected_today' => $collected,
            'total_outstanding' => $outstanding,
        ];

        // Send to all admin/super_admin users
        $admins = User::role(['super_admin', 'admin'])->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->queue(new DailyCollectionSummaryMail($stats));
        }

        $this->info("Daily collection summary sent to " . $admins->count() . " admins.");

        return self::SUCCESS;
    }
}
