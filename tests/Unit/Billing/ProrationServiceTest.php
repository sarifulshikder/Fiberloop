<?php

use App\Services\Billing\ProrationService;
use Carbon\Carbon;

it('calculates activation proration correctly', function () {
    $service = new ProrationService();
    
    // Monthly package price: 1000 BDT = 100000 poysha
    $monthlyPrice = 100000;
    
    // Cycle: Jan 1 to Jan 31 (31 days)
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    
    // Customer activated on Jan 15 (should pay for 17 days: Jan 15-31 inclusive)
    $activationDate = Carbon::parse('2026-01-15');
    
    $proratedAmount = $service->calculateActivationProration(
        $monthlyPrice,
        $activationDate,
        $cycleStart,
        $cycleEnd
    );
    
    // Expected: 100000 * 17 / 31 = 54838.709... -> 54839 poysha
    $expected = (int) round(100000 * 17 / 31);
    
    expect($proratedAmount)->toBe($expected);
});

it('calculates activation proration for full cycle', function () {
    $service = new ProrationService();
    
    $monthlyPrice = 100000;
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    $activationDate = Carbon::parse('2026-01-01'); // Activated on day 1
    
    $proratedAmount = $service->calculateActivationProration(
        $monthlyPrice,
        $activationDate,
        $cycleStart,
        $cycleEnd
    );
    
    // Should be full amount for full cycle
    expect($proratedAmount)->toBe(100000);
});

it('calculates activation proration for end of cycle', function () {
    $service = new ProrationService();
    
    $monthlyPrice = 100000;
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    $activationDate = Carbon::parse('2026-01-31'); // Activated on last day
    
    $proratedAmount = $service->calculateActivationProration(
        $monthlyPrice,
        $activationDate,
        $cycleStart,
        $cycleEnd
    );
    
    // Should be 1 day's worth
    expect($proratedAmount)->toBe((int) round(100000 / 31));
});

it('calculates upgrade proration correctly', function () {
    $service = new ProrationService();
    
    $oldPrice = 100000; // 1000 BDT
    $newPrice = 150000; // 1500 BDT
    $priceDifference = 50000; // 500 BDT difference
    
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    $changeDate = Carbon::parse('2026-01-15'); // Changed on day 15
    
    $proratedAmount = $service->calculateUpgradeProration(
        $oldPrice,
        $newPrice,
        $changeDate,
        $cycleStart,
        $cycleEnd
    );
    
    // Should pay difference for remaining 17 days: 50000 * 17 / 31
    $expected = (int) round(50000 * 17 / 31);
    expect($proratedAmount)->toBe($expected);
});

it('returns zero for upgrade proration when no price difference', function () {
    $service = new ProrationService();
    
    $price = 100000;
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    $changeDate = Carbon::parse('2026-01-15');
    
    $proratedAmount = $service->calculateUpgradeProration(
        $price,
        $price, // Same price
        $changeDate,
        $cycleStart,
        $cycleEnd
    );
    
    expect($proratedAmount)->toBe(0);
});

it('calculates downgrade proration correctly', function () {
    $service = new ProrationService();
    
    $oldPrice = 150000; // 1500 BDT
    $newPrice = 100000; // 1000 BDT
    $priceDifference = 50000; // 500 BDT difference
    
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    $changeDate = Carbon::parse('2026-01-15'); // Changed on day 15
    
    $creditAmount = $service->calculateDowngradeProration(
        $oldPrice,
        $newPrice,
        $changeDate,
        $cycleStart,
        $cycleEnd
    );
    
    // Should get credit for difference for remaining 17 days: 50000 * 17 / 31
    $expected = (int) round(50000 * 17 / 31);
    expect($creditAmount)->toBe($expected);
});

it('calculates suspension proration correctly', function () {
    $service = new ProrationService();
    
    $monthlyPrice = 100000;
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    $suspensionDate = Carbon::parse('2026-01-15'); // Suspended on day 15
    
    $proratedAmount = $service->calculateSuspensionProration(
        $monthlyPrice,
        $suspensionDate,
        $cycleStart,
        $cycleEnd
    );
    
    // Should pay for 15 days used: 100000 * 15 / 31
    $expected = (int) round(100000 * 15 / 31);
    expect($proratedAmount)->toBe($expected);
});

it('returns zero for suspension proration when suspended on first day', function () {
    $service = new ProrationService();
    
    $monthlyPrice = 100000;
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    $suspensionDate = Carbon::parse('2026-01-01'); // Suspended on day 1
    
    $proratedAmount = $service->calculateSuspensionProration(
        $monthlyPrice,
        $suspensionDate,
        $cycleStart,
        $cycleEnd
    );
    
    expect($proratedAmount)->toBe((int) round(100000 / 31));
});

it('returns zero for suspension proration when suspended after cycle end', function () {
    $service = new ProrationService();
    
    $monthlyPrice = 100000;
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    $suspensionDate = Carbon::parse('2026-02-01'); // Suspended after cycle
    
    $proratedAmount = $service->calculateSuspensionProration(
        $monthlyPrice,
        $suspensionDate,
        $cycleStart,
        $cycleEnd
    );
    
    expect($proratedAmount)->toBe(0);
});

it('calculates daily rate correctly', function () {
    $service = new ProrationService();
    
    $monthlyPrice = 100000; // 1000 BDT
    $daysInMonth = 30;
    
    $dailyRate = $service->calculateDailyRate($monthlyPrice, $daysInMonth);
    
    // 100000 / 30 = 3333.333... -> 3333 poysha
    expect($dailyRate)->toBe((int) round(100000 / 30));
});

it('calculates days between dates correctly', function () {
    $service = new ProrationService();
    
    $start = Carbon::parse('2026-01-01');
    $end = Carbon::parse('2026-01-05');
    
    $days = $service->calculateDays($start, $end);
    
    // Jan 1, 2, 3, 4, 5 = 5 days inclusive
    expect($days)->toBe(5);
});

it('calculates prorated amount by day ratio correctly', function () {
    $service = new ProrationService();
    
    $fullAmount = 100000;
    $numerator = 15; // 15 days
    $denominator = 30; // 30 days in month
    
    $proratedAmount = $service->calculateByDayRatio($fullAmount, $numerator, $denominator);
    
    expect($proratedAmount)->toBe((int) round(100000 * 15 / 30));
});

it('handles zero denominator in day ratio calculation', function () {
    $service = new ProrationService();
    
    $fullAmount = 100000;
    $numerator = 15;
    $denominator = 0;
    
    $proratedAmount = $service->calculateByDayRatio($fullAmount, $numerator, $denominator);
    
    expect($proratedAmount)->toBe(0);
});

it('handles proration for different month lengths', function () {
    $service = new ProrationService();
    
    // February (28 days in 2026)
    $monthlyPrice = 100000;
    $cycleStart = Carbon::parse('2026-02-01');
    $cycleEnd = Carbon::parse('2026-02-28');
    $activationDate = Carbon::parse('2026-02-14');
    
    $proratedAmount = $service->calculateActivationProration(
        $monthlyPrice,
        $activationDate,
        $cycleStart,
        $cycleEnd
    );
    
    // Should be 100000 * 15 / 28
    $expected = (int) round(100000 * 15 / 28);
    expect($proratedAmount)->toBe($expected);
});
