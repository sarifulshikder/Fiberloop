<?php

namespace App\Jobs;

use App\Exports\CustomerDataExport;
use App\Models\Customer;
use App\Models\CustomerDataExportRequest;
use App\Notifications\CustomerDataExportReady;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;

/**
 * Job to process customer data export requests asynchronously.
 */
class ProcessCustomerDataExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 300; // 5 minutes

    public function __construct(
        public Customer $customer,
        public CustomerDataExportRequest $exportRequest
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(Excel $excel): void
    {
        try {
            $this->exportRequest->update([
                'status' => 'processing',
                'failed_at' => null,
            ]);

            // Generate the export
            $export = new CustomerDataExport($this->customer);
            $export->setRequestedDataTypes($this->exportRequest->requested_data_types);
            $export->setFormat($this->exportRequest->format);

            // Generate the file
            $fileName = 'customer_' . $this->customer->uuid . '_export_' . now()->format('Ymd_His') . '.' . $this->exportRequest->format;
            $filePath = 'app/exports/' . $fileName;
            $fullPath = storage_path($filePath);

            // Ensure directory exists
            Storage::disk('local')->makeDirectory('exports');

            // Export based on format
            if ($this->exportRequest->format === 'json') {
                $content = $export->generate();
                Storage::disk('local')->put('app/exports/' . $fileName, json_encode($content, JSON_PRETTY_PRINT));
            } else {
                // Use Laravel Excel for CSV and XLSX
                $excel->store($export, $fileName, 'local');
                // Move to exports directory
                if (Storage::disk('local')->exists($fileName)) {
                    Storage::disk('local')->move($fileName, 'app/exports/' . $fileName);
                }
            }

            // Update the export request
            $this->exportRequest->update([
                'status' => 'completed',
                'completed_at' => now(),
                'download_url' => 'exports/' . $fileName,
                'download_expires_at' => now()->addDays(7),
            ]);

            // Notify customer
            $this->customer->notify(new CustomerDataExportReady($this->exportRequest));

            Log::info("Customer data export completed", [
                'customer_id' => $this->customer->id,
                'request_id' => $this->exportRequest->uuid,
                'file' => $fileName,
            ]);

        } catch (\Exception $e) {
            $this->exportRequest->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);

            Log::error("Customer data export failed", [
                'customer_id' => $this->customer->id,
                'request_id' => $this->exportRequest->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->exportRequest->update([
            'status' => 'failed',
            'failed_at' => now(),
        ]);

        Log::error("Customer data export job failed", [
            'customer_id' => $this->customer->id,
            'request_id' => $this->exportRequest->uuid,
            'error' => $exception->getMessage(),
        ]);
    }
}
