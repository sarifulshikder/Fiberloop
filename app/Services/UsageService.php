<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\RadAcct;
use App\Models\RadiusCustomer;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class UsageService
{
    /**
     * Get current usage summary for a customer.
     */
    public function getCustomerUsage(Customer $customer): array
    {
        $subscription = $customer->subscriptions()->active()->first();
        
        if (!$subscription) {
            return $this->emptyUsageSummary($customer);
        }

        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)
            ->where('subscription_id', $subscription->id)
            ->first();

        $username = $radiusCustomer?->radius_username ?? $customer->radius_username;

        if (!$username) {
            return $this->emptyUsageSummary($customer);
        }

        // Get active session
        $activeSession = RadAcct::where('username', $username)
            ->whereNull('acctstoptime')
            ->latest('acctstarttime')
            ->first();

        // Get current billing period usage
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();
        
        $monthlyUsage = RadAcct::where('username', $username)
            ->where('acctstarttime', '>=', $currentMonthStart)
            ->where('acctstarttime', '<=', $currentMonthEnd)
            ->sum('acctinputoctets') + 
            RadAcct::where('username', $username)
                ->where('acctstarttime', '>=', $currentMonthStart)
                ->where('acctstarttime', '<=', $currentMonthEnd)
                ->sum('acctoutputoctets');

        // Get all-time usage
        $allTimeUsage = RadAcct::where('username', $username)
            ->sum('acctinputoctets') + 
            RadAcct::where('username', $username)
                ->sum('acctoutputoctets');

        // Get session history for current month
        $sessions = RadAcct::where('username', $username)
            ->where('acctstarttime', '>=', $currentMonthStart)
            ->where('acctstarttime', '<=', $currentMonthEnd)
            ->orderBy('acctstarttime', 'desc')
            ->get();

        return [
            'customer_id' => $customer->id,
            'username' => $username,
            'subscription_id' => $subscription->id,
            'is_online' => $activeSession !== null,
            'active_session' => $activeSession ? $this->formatSession($activeSession) : null,
            'current_month_usage' => $monthlyUsage,
            'current_month_usage_formatted' => $this->formatBytes($monthlyUsage),
            'all_time_usage' => $allTimeUsage,
            'all_time_usage_formatted' => $this->formatBytes($allTimeUsage),
            'sessions_count' => $sessions->count(),
            'total_session_duration' => $sessions->sum('acctsessiontime'),
            'total_session_duration_formatted' => $this->formatDuration($sessions->sum('acctsessiontime')),
            'fup_limit' => $subscription->package?->fup_threshold ?? 0,
            'fup_limit_formatted' => $this->formatBytes($subscription->package?->fup_threshold ?? 0),
            'fup_usage_percentage' => $this->calculateFupPercentage($monthlyUsage, $subscription->package?->fup_threshold ?? 0),
            'fup_remaining' => $this->calculateFupRemaining($monthlyUsage, $subscription->package?->fup_threshold ?? 0),
            'fup_remaining_formatted' => $this->formatBytes($this->calculateFupRemaining($monthlyUsage, $subscription->package?->fup_threshold ?? 0)),
            'last_updated' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get session history for a customer.
     */
    public function getSessionHistory(Customer $customer, int $limit = 20): Collection
    {
        $subscription = $customer->subscriptions()->active()->first();
        $username = $subscription?->radiusCustomer?->radius_username ?? $customer->radius_username;

        if (!$username) {
            return collect();
        }

        return RadAcct::where('username', $username)
            ->orderBy('acctstarttime', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($session) => $this->formatSession($session));
    }

    /**
     * Get near real-time usage (last 5 minutes).
     */
    public function getNearRealtimeUsage(Customer $customer): array
    {
        $subscription = $customer->subscriptions()->active()->first();
        $username = $subscription?->radiusCustomer?->radius_username ?? $customer->radius_username;

        if (!$username) {
            return $this->emptyUsageSummary($customer);
        }

        // Check for active session first
        $activeSession = RadAcct::where('username', $username)
            ->whereNull('acctstoptime')
            ->latest('acctstarttime')
            ->first();

        if ($activeSession) {
            return $this->formatSession($activeSession) + [
                'current_month_usage' => RadAcct::where('username', $username)
                    ->where('acctstarttime', '>=', now()->startOfMonth())
                    ->sum('acctinputoctets') + 
                    RadAcct::where('username', $username)
                        ->where('acctstarttime', '>=', now()->startOfMonth())
                        ->sum('acctoutputoctets'),
                'fup_limit' => $subscription->package?->fup_threshold ?? 0,
                'fup_usage_percentage' => $this->calculateFupPercentage(
                    RadAcct::where('username', $username)
                        ->where('acctstarttime', '>=', now()->startOfMonth())
                        ->sum('acctinputoctets') + 
                    RadAcct::where('username', $username)
                        ->where('acctstarttime', '>=', now()->startOfMonth())
                        ->sum('acctoutputoctets'),
                    $subscription->package?->fup_threshold ?? 0
                ),
            ];
        }

        // If no active session, return last session data
        $lastSession = RadAcct::where('username', $username)
            ->latest('acctstarttime')
            ->first();

        return $lastSession ? $this->formatSession($lastSession) : $this->emptyUsageSummary($customer);
    }

    /**
     * Format a RADIUS accounting session.
     */
    protected function formatSession(RadAcct $session): array
    {
        $dataUsed = ($session->acctinputoctets ?? 0) + ($session->acctoutputoctets ?? 0);
        
        return [
            'session_id' => $session->acctsessionid,
            'username' => $session->username,
            'nas_ip_address' => $session->nasipaddress,
            'nas_port' => $session->nasportid,
            'framed_ip_address' => $session->framedipaddress,
            'session_start' => $session->acctstarttime?->toDateTimeString(),
            'session_stop' => $session->acctstoptime?->toDateTimeString(),
            'session_duration' => $session->acctsessiontime ?? 0,
            'session_duration_formatted' => $this->formatDuration($session->acctsessiontime ?? 0),
            'data_used' => $dataUsed,
            'data_used_formatted' => $this->formatBytes($dataUsed),
            'data_uploaded' => $session->acctoutputoctets ?? 0,
            'data_uploaded_formatted' => $this->formatBytes($session->acctoutputoctets ?? 0),
            'data_downloaded' => $session->acctinputoctets ?? 0,
            'data_downloaded_formatted' => $this->formatBytes($session->acctinputoctets ?? 0),
            'is_active' => $session->acctstoptime === null,
        ];
    }

    /**
     * Format bytes to human-readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Format duration in seconds to human-readable format.
     */
    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return round($seconds / 60, 0) . 'm';
        }
        if ($seconds < 86400) {
            return round($seconds / 3600, 1) . 'h';
        }
        return round($seconds / 86400, 1) . 'd';
    }

    /**
     * Calculate FUP usage percentage.
     */
    protected function calculateFupPercentage(int $used, int $limit): float
    {
        if ($limit <= 0) {
            return 0;
        }
        return min(100, ($used / $limit) * 100);
    }

    /**
     * Calculate remaining FUP data.
     */
    protected function calculateFupRemaining(int $used, int $limit): int
    {
        if ($limit <= 0) {
            return 0;
        }
        return max(0, $limit - $used);
    }

    /**
     * Create an empty usage summary.
     */
    protected function emptyUsageSummary(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'username' => null,
            'subscription_id' => null,
            'is_online' => false,
            'active_session' => null,
            'current_month_usage' => 0,
            'current_month_usage_formatted' => '0 B',
            'all_time_usage' => 0,
            'all_time_usage_formatted' => '0 B',
            'sessions_count' => 0,
            'total_session_duration' => 0,
            'total_session_duration_formatted' => '0s',
            'fup_limit' => 0,
            'fup_limit_formatted' => '0 B',
            'fup_usage_percentage' => 0,
            'fup_remaining' => 0,
            'fup_remaining_formatted' => '0 B',
            'last_updated' => now()->toDateTimeString(),
        ];
    }
}
