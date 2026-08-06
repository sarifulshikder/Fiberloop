<?php

namespace App\Services\Radius;

use App\Models\RadAcct;
use App\Models\RadiusCustomer;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class RadiusSessionService
{
    public function __construct(
        protected RadiusCoaService $coaService
    ) {}

    /**
     * Fetch all currently active (online) RADIUS sessions.
     */
    public function getActiveSessions(?int $perPage = 25): LengthAwarePaginator
    {
        return RadAcct::whereNull('acctstoptime')
            ->orderBy('acctstarttime', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get active session for a specific RADIUS username.
     */
    public function getCustomerActiveSession(string $username): ?RadAcct
    {
        return RadAcct::where('username', $username)
            ->whereNull('acctstoptime')
            ->orderBy('acctstarttime', 'desc')
            ->first();
    }

    /**
     * Get historical sessions for a specific RADIUS username.
     */
    public function getCustomerSessionHistory(string $username, int $limit = 50): Collection
    {
        return RadAcct::where('username', $username)
            ->orderBy('acctstarttime', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Compute data usage statistics (input, output, total bytes, total duration) for a user within a period.
     */
    public function getUserUsageStats(string $username, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->startOfMonth();
        $endDate = $endDate ?? Carbon::now()->endOfDay();

        $query = RadAcct::where('username', $username)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('acctstarttime', [$startDate, $endDate])
                  ->orWhereNull('acctstarttime');
            });

        $inputBytes = (int) $query->sum('acctinputoctets');
        $outputBytes = (int) $query->sum('acctoutputoctets');
        $totalSessionSeconds = (int) $query->sum('acctsessiontime');
        $sessionCount = $query->count();

        return [
            'username' => $username,
            'period_start' => $startDate->toDateTimeString(),
            'period_end' => $endDate->toDateTimeString(),
            'input_bytes' => $inputBytes,
            'output_bytes' => $outputBytes,
            'total_bytes' => $inputBytes + $outputBytes,
            'input_formatted' => $this->formatBytes($inputBytes),
            'output_formatted' => $this->formatBytes($outputBytes),
            'total_formatted' => $this->formatBytes($inputBytes + $outputBytes),
            'total_session_seconds' => $totalSessionSeconds,
            'total_session_hours' => round($totalSessionSeconds / 3600, 2),
            'session_count' => $sessionCount,
        ];
    }

    /**
     * Force disconnect an active online session via CoA/PoD.
     */
    public function disconnectSession(string $username): bool
    {
        $activeSession = $this->getCustomerActiveSession($username);
        $nasIp = $activeSession?->nasipaddress;

        return $this->coaService->disconnectUser($username, $nasIp);
    }

    /**
     * Format raw bytes into human readable format (MB, GB, TB).
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
