<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerDataDeletionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job to process customer data deletion requests asynchronously.
 * This implements the "right to be forgotten" with a safety delay.
 */
class ProcessCustomerDataDeletion implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1; // Only attempt once for data deletion

    public function __construct(
        public Customer $customer,
        public CustomerDataDeletionRequest $deletionRequest
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::beginTransaction();

        try {
            $this->deletionRequest->update([
                'status' => 'processing',
                'failed_at' => null,
            ]);

            $deletionReport = [
                'started_at' => now()->toDateTimeString(),
                'customer_id' => $this->customer->id,
                'customer_uuid' => $this->customer->uuid,
                'deleted_records' => [],
                'anonymized_records' => [],
                'errors' => [],
            ];

            // Process deletion based on scope
            if ($this->deletionRequest->scope === 'all') {
                $deletionReport = $this->deleteAllCustomerData($this->customer, $deletionReport);
            } else {
                $deletionReport = $this->deleteSpecificCustomerData($this->customer, $deletionReport);
            }

            // Mark as completed
            $this->deletionRequest->update([
                'status' => 'completed',
                'completed_at' => now(),
                'deletion_report' => $deletionReport,
            ]);

            DB::commit();

            Log::info("Customer data deletion completed", [
                'customer_id' => $this->customer->id,
                'request_id' => $this->deletionRequest->uuid,
                'report' => $deletionReport,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            $this->deletionRequest->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);

            Log::error("Customer data deletion failed", [
                'customer_id' => $this->customer->id,
                'request_id' => $this->deletionRequest->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete all customer data (GDPR right to be forgotten).
     */
    protected function deleteAllCustomerData(Customer $customer, array $report): array
    {
        // Soft delete the customer (preserves data for audit but marks as deleted)
        $customer->delete();
        $report['deleted_records'][] = [
            'model' => 'Customer',
            'id' => $customer->id,
            'action' => 'soft_deleted',
        ];

        // Anonymize sensitive data instead of hard deleting for financial records
        $this->anonymizeCustomerData($customer, $report);

        return $report;
    }

    /**
     * Delete specific customer data.
     */
    protected function deleteSpecificCustomerData(Customer $customer, array $report): array
    {
        // For specific deletions, we would have more granular control
        // For now, same as all but with confirmation
        return $this->deleteAllCustomerData($customer, $report);
    }

    /**
     * Anonymize customer data instead of hard deleting.
     * This preserves referential integrity while removing personal data.
     */
    protected function anonymizeCustomerData(Customer $customer, array &$report): void
    {
        // Anonymize personal data
        $updates = [
            'first_name' => 'Deleted',
            'last_name' => 'Customer',
            'email' => 'deleted_' . $customer->uuid . '@deleted.example.com',
            'phone' => '0000000000',
            'alternate_phone' => null,
            'date_of_birth' => null,
            'gender' => null,
            'nid_number' => null,
            'nid_front_photo' => null,
            'nid_back_photo' => null,
            'signature_photo' => null,
            'service_address' => 'Deleted',
            'billing_address' => 'Deleted',
            'service_latitude' => null,
            'service_longitude' => null,
            'radius_username' => null,
            'radius_password' => null,
            'static_ip' => null,
            'mac_address' => null,
            'status' => 'terminated',
            'termination_reason' => 'Customer requested data deletion',
            'fcm_token' => null,
            'promotional_sms_opt_in' => false,
            'promotional_email_opt_in' => false,
        ];

        $customer->update($updates);

        $report['anonymized_records'][] = [
            'model' => 'Customer',
            'id' => $customer->id,
            'action' => 'anonymized',
            'fields_updated' => array_keys($updates),
        ];

        // Also handle related models
        $this->anonymizeRelatedModels($customer, $report);
    }

    /**
     * Anonymize related models.
     */
    protected function anonymizeRelatedModels(Customer $customer, array &$report): void
    {
        // Anonymize user if linked
        if ($customer->user) {
            $customer->user->update([
                'name' => 'Deleted User',
                'email' => 'deleted_user_' . $customer->user->uuid . '@deleted.example.com',
                'phone' => null,
            ]);

            $report['anonymized_records'][] = [
                'model' => 'User',
                'id' => $customer->user->id,
                'action' => 'anonymized',
            ];
        }

        // Note: Invoices, payments, and other financial records should be preserved
        // but with customer PII removed for audit/legal compliance
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->deletionRequest->update([
            'status' => 'failed',
            'failed_at' => now(),
        ]);

        Log::error("Customer data deletion job failed", [
            'customer_id' => $this->customer->id,
            'request_id' => $this->deletionRequest->uuid,
            'error' => $exception->getMessage(),
        ]);
    }
}
