<?php

namespace App\Services\Billing;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Handles proration calculations for billing scenarios.
 * All amounts are in poysha (BDT x 100).
 */
class ProrationService
{
    /**
     * Calculate prorated amount for mid-cycle activation.
     *
     * @param int $packagePrice Monthly price in poysha
     * @param CarbonInterface $activationDate When service started
     * @param CarbonInterface $cycleStart Start of billing cycle
     * @param CarbonInterface $cycleEnd End of billing cycle
     * @return int Prorated amount in poysha
     */
    public function calculateActivationProration(
        int $packagePrice,
        CarbonInterface $activationDate,
        CarbonInterface $cycleStart,
        CarbonInterface $cycleEnd
    ): int {
        $totalDays = $cycleStart->diffInDays($cycleEnd) + 1;
        $remainingDays = $activationDate->diffInDays($cycleEnd) + 1;
        
        if ($remainingDays <= 0) {
            return 0;
        }
        
        return (int) round($packagePrice * $remainingDays / $totalDays);
    }

    /**
     * Calculate prorated amount for package upgrade.
     * Customer pays difference for remaining days.
     *
     * @param int $oldPrice Old package price in poysha
     * @param int $newPrice New package price in poysha
     * @param CarbonInterface $changeDate When change takes effect
     * @param CarbonInterface $cycleStart Start of billing cycle
     * @param CarbonInterface $cycleEnd End of billing cycle
     * @return int Prorated difference in poysha
     */
    public function calculateUpgradeProration(
        int $oldPrice,
        int $newPrice,
        CarbonInterface $changeDate,
        CarbonInterface $cycleStart,
        CarbonInterface $cycleEnd
    ): int {
        $priceDifference = $newPrice - $oldPrice;
        $totalDays = $cycleStart->diffInDays($cycleEnd) + 1;
        $remainingDays = $changeDate->diffInDays($cycleEnd) + 1;
        
        if ($remainingDays <= 0 || $priceDifference <= 0) {
            return 0;
        }
        
        return (int) round($priceDifference * $remainingDays / $totalDays);
    }

    /**
     * Calculate prorated credit for package downgrade.
     * Customer gets credit for remaining days at price difference.
     *
     * @param int $oldPrice Old package price in poysha
     * @param int $newPrice New package price in poysha
     * @param CarbonInterface $changeDate When change takes effect
     * @param CarbonInterface $cycleStart Start of billing cycle
     * @param CarbonInterface $cycleEnd End of billing cycle
     * @return int Prorated credit in poysha (positive value)
     */
    public function calculateDowngradeProration(
        int $oldPrice,
        int $newPrice,
        CarbonInterface $changeDate,
        CarbonInterface $cycleStart,
        CarbonInterface $cycleEnd
    ): int {
        $priceDifference = $oldPrice - $newPrice;
        $totalDays = $cycleStart->diffInDays($cycleEnd) + 1;
        $remainingDays = $changeDate->diffInDays($cycleEnd) + 1;
        
        if ($remainingDays <= 0 || $priceDifference <= 0) {
            return 0;
        }
        
        return (int) round($priceDifference * $remainingDays / $totalDays);
    }

    /**
     * Calculate prorated amount for mid-cycle suspension.
     * Customer pays for days used.
     *
     * @param int $packagePrice Monthly price in poysha
     * @param CarbonInterface $suspensionDate When service was suspended
     * @param CarbonInterface $cycleStart Start of billing cycle
     * @param CarbonInterface $cycleEnd End of billing cycle
     * @return int Prorated amount in poysha
     */
    public function calculateSuspensionProration(
        int $packagePrice,
        CarbonInterface $suspensionDate,
        CarbonInterface $cycleStart,
        CarbonInterface $cycleEnd
    ): int {
        $totalDays = $cycleStart->diffInDays($cycleEnd) + 1;
        $usedDays = $cycleStart->diffInDays($suspensionDate) + 1;
        
        if ($usedDays <= 0) {
            return 0;
        }
        
        return (int) round($packagePrice * $usedDays / $totalDays);
    }

    /**
     * Calculate daily rate from monthly price.
     *
     * @param int $monthlyPrice Price in poysha
     * @param int $daysInMonth Days in the billing cycle (default 30)
     * @return int Daily rate in poysha
     */
    public function calculateDailyRate(int $monthlyPrice, int $daysInMonth = 30): int
    {
        return (int) round($monthlyPrice / $daysInMonth);
    }

    /**
     * Calculate days between two dates (inclusive of start date).
     */
    public function calculateDays(CarbonInterface $start, CarbonInterface $end): int
    {
        return $start->diffInDays($end) + 1;
    }

    /**
     * Calculate prorated amount based on day ratio.
     *
     * @param int $fullAmount Full amount in poysha
     * @param int $numerator Days used/remaining
     * @param int $denominator Total days in cycle
     * @return int Prorated amount in poysha
     */
    public function calculateByDayRatio(int $fullAmount, int $numerator, int $denominator): int
    {
        if ($denominator === 0) {
            return 0;
        }
        
        return (int) round($fullAmount * $numerator / $denominator);
    }
}
