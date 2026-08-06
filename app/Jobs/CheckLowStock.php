<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Notifications\LowStockAlert;
use App\Services\InventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Check for low stock levels and send alerts.
 * This job runs on a schedule (daily or more frequently) to check
 * inventory levels against configured thresholds.
 */
class CheckLowStock implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The default thresholds for each item type.
     */
    protected array $defaultThresholds = [
        'router' => 5,
        'onu' => 20,
        'cable' => 100,
        'switch' => 10,
        'olt' => 3,
        'sfp' => 15,
        'accessory' => 50,
        'other' => 10,
    ];

    /**
     * Execute the job.
     */
    public function handle(InventoryService $inventoryService): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->checkTenantStock($tenant, $inventoryService);
        }
    }

    /**
     * Check stock levels for a specific tenant.
     */
    protected function checkTenantStock(Tenant $tenant, InventoryService $inventoryService): void
    {
        $thresholds = $this->getThresholdsForTenant($tenant);
        $alerts = $inventoryService->checkLowStock($thresholds);

        if ($alerts->isEmpty()) {
            Log::debug("No low stock alerts for tenant {$tenant->id}");
            return;
        }

        // Get admin users for this tenant
        $admins = $tenant->users()
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['admin', 'super_admin', 'noc_engineer']);
            })
            ->get();

        if ($admins->isEmpty()) {
            Log::warning("No admin users found for tenant {$tenant->id} to send low stock alerts");
            return;
        }

        // Send notifications
        foreach ($admins as $admin) {
            try {
                Notification::send($admin, new LowStockAlert($alerts, $tenant));
                Log::info("Low stock alert sent to user {$admin->id} for tenant {$tenant->id}");
            } catch (\Exception $e) {
                Log::error("Failed to send low stock alert to user {$admin->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Get stock thresholds for a tenant.
     * This can be customized per tenant in the future.
     */
    protected function getThresholdsForTenant(Tenant $tenant): array
    {
        // In the future, this could come from tenant settings
        return $this->defaultThresholds;
    }

    /**
     * Get the display name for the job.
     */
    public function displayName(): string
    {
        return 'Check Low Stock Levels';
    }
}
