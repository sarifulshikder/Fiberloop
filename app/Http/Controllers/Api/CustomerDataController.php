<?php

namespace App\Http\Controllers\Api;

use App\Exports\CustomerDataExport;
use App\Http\Requests\Api\CustomerDataRequest;
use App\Jobs\ProcessCustomerDataDeletion;
use App\Jobs\ProcessCustomerDataExport;
use App\Models\Customer;
use App\Notifications\CustomerDataExportReady;
use App\Notifications\CustomerDataDeletionConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;

/**
 * Controller for handling customer data export and deletion requests.
 * This implements GDPR-style data portability and right to erasure.
 */
class CustomerDataController extends Controller
{
    public function __construct(private Excel $excel)
    {
    }

    /**
     * Request export of customer's personal data.
     * This initiates an asynchronous job to compile and export all customer data.
     */
    public function requestExport(CustomerDataRequest $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        // Create export request record
        $exportRequest = $customer->dataExportRequests()->create([
            'requested_at' => now(),
            'status' => 'processing',
            'requested_data_types' => $request->input('data_types', ['profile', 'subscriptions', 'invoices', 'payments']),
            'format' => $request->input('format', 'json'),
        ]);

        // Dispatch job to process the export asynchronously
        ProcessCustomerDataExport::dispatch($customer, $exportRequest);

        return response()->json([
            'success' => true,
            'message' => 'Your data export request has been received and is being processed',
            'data' => [
                'request_id' => $exportRequest->uuid,
                'status' => 'processing',
                'estimated_completion' => 'within 24 hours',
            ],
        ]);
    }

    /**
     * Check the status of a data export request.
     */
    public function exportStatus(string $requestId): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Find the export request
        $exportRequest = $customer->dataExportRequests()
            ->where('uuid', $requestId)
            ->first();

        if (!$exportRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Export request not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $exportRequest->uuid,
                'status' => $exportRequest->status,
                'requested_at' => $exportRequest->requested_at->toDateTimeString(),
                'completed_at' => $exportRequest->completed_at?->toDateTimeString(),
                'download_url' => $exportRequest->download_url,
                'download_expires_at' => $exportRequest->download_expires_at?->toDateTimeString(),
                'data_types' => $exportRequest->requested_data_types,
                'format' => $exportRequest->format,
            ],
        ]);
    }

    /**
     * Download the exported data.
     */
    public function downloadExport(string $requestId): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $exportRequest = $customer->dataExportRequests()
            ->where('uuid', $requestId)
            ->first();

        if (!$exportRequest || !$exportRequest->download_url) {
            return response()->json([
                'success' => false,
                'message' => 'Export request not found or not ready for download',
            ], 404);
        }

        if ($exportRequest->download_expires_at && $exportRequest->download_expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Download link has expired',
            ], 403);
        }

        // Return the file for download
        $filePath = storage_path('app/exports/' . basename($exportRequest->download_url));
        
        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Export file not found',
            ], 404);
        }

        return response()->download($filePath, 'my_data_export.' . $exportRequest->format, [
            'Content-Type' => $exportRequest->format === 'json' ? 'application/json' : 'text/csv',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Request deletion of customer's personal data.
     * This implements the "right to be forgotten".
     */
    public function requestDeletion(CustomerDataRequest $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        // Create deletion request record
        $deletionRequest = $customer->dataDeletionRequests()->create([
            'requested_at' => now(),
            'status' => 'processing',
            'scope' => $request->input('scope', 'all'), // 'all', 'specific'
            'confirmation_required' => true,
            'confirmation_sent_at' => null,
        ]);

        // Send confirmation notification
        $customer->notify(new CustomerDataDeletionConfirmation($deletionRequest));

        return response()->json([
            'success' => true,
            'message' => 'Your data deletion request has been received. A confirmation has been sent to your email.',
            'data' => [
                'request_id' => $deletionRequest->uuid,
                'status' => 'confirmation_required',
                'scope' => $deletionRequest->scope,
            ],
        ]);
    }

    /**
     * Confirm data deletion request.
     */
    public function confirmDeletion(string $requestId, string $confirmationToken): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $deletionRequest = $customer->dataDeletionRequests()
            ->where('uuid', $requestId)
            ->first();

        if (!$deletionRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Deletion request not found',
            ], 404);
        }

        // Verify confirmation token
        if ($deletionRequest->confirmation_token !== $confirmationToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid confirmation token',
            ], 403);
        }

        if ($deletionRequest->confirmation_confirmed_at) {
            return response()->json([
                'success' => false,
                'message' => 'This deletion request has already been confirmed',
            ], 400);
        }

        // Update request as confirmed
        $deletionRequest->update([
            'confirmation_confirmed_at' => now(),
            'status' => 'scheduled',
        ]);

        // Dispatch job to process deletion asynchronously
        ProcessCustomerDataDeletion::dispatch($customer, $deletionRequest)
            ->delay(now()->addHours(24)); // 24 hour delay for safety

        return response()->json([
            'success' => true,
            'message' => 'Your data deletion request has been confirmed and will be processed within 24 hours',
            'data' => [
                'request_id' => $deletionRequest->uuid,
                'status' => 'scheduled',
                'scheduled_for' => now()->addHours(24)->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Check the status of a data deletion request.
     */
    public function deletionStatus(string $requestId): JsonResponse
    {
        $customer = Auth::guard('sanctum')->user();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $deletionRequest = $customer->dataDeletionRequests()
            ->where('uuid', $requestId)
            ->first();

        if (!$deletionRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Deletion request not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $deletionRequest->uuid,
                'status' => $deletionRequest->status,
                'requested_at' => $deletionRequest->requested_at->toDateTimeString(),
                'confirmation_required' => $deletionRequest->confirmation_required,
                'confirmation_sent_at' => $deletionRequest->confirmation_sent_at?->toDateTimeString(),
                'confirmation_confirmed_at' => $deletionRequest->confirmation_confirmed_at?->toDateTimeString(),
                'completed_at' => $deletionRequest->completed_at?->toDateTimeString(),
                'scope' => $deletionRequest->scope,
            ],
        ]);
    }

    /**
     * Export data immediately (for admin/staff use only).
     * This bypasses the async job for administrative purposes.
     */
    public function adminExport(Request $request, string $customerId): JsonResponse
    {
        $this->authorize('exportCustomerData');

        $customer = Customer::findOrFail($customerId);

        $exportRequest = $customer->dataExportRequests()->create([
            'requested_at' => now(),
            'status' => 'completed',
            'requested_data_types' => ['profile', 'subscriptions', 'invoices', 'payments', 'tickets', 'notes'],
            'format' => 'json',
            'completed_at' => now(),
            'requested_by_admin' => auth()->id(),
        ]);

        // Generate and return export immediately
        $fileName = 'customer_' . $customer->uuid . '_' . now()->format('Ymd_His') . '.json';
        $filePath = 'exports/' . $fileName;
        
        // Create the export
        $exportService = new CustomerDataExport($customer);
        $exportContent = $exportService->generate();
        
        Storage::disk('local')->put('app/' . $filePath, json_encode($exportContent, JSON_PRETTY_PRINT));

        $exportRequest->update([
            'download_url' => $filePath,
            'download_expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer data export created successfully',
            'data' => [
                'request_id' => $exportRequest->uuid,
                'download_url' => $filePath,
                'expires_at' => now()->addDays(7)->toDateTimeString(),
            ],
        ]);
    }
}
