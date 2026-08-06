<?php

namespace App\Exports;

use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export class for customer data portability.
 * This class handles exporting customer data in various formats.
 */
class CustomerDataExport implements FromCollection, WithHeadings, ShouldQueue
{
    use Exportable;

    protected Customer $customer;
    protected array $requestedDataTypes = ['profile', 'subscriptions', 'invoices', 'payments'];
    protected string $format = 'json';

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
    }

    /**
     * Set the requested data types.
     */
    public function setRequestedDataTypes(array $dataTypes): self
    {
        $this->requestedDataTypes = $dataTypes;
        return $this;
    }

    /**
     * Set the export format.
     */
    public function setFormat(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Get the data for JSON export.
     */
    public function generate(): array
    {
        $data = [];

        if (in_array('profile', $this->requestedDataTypes, true)) {
            $data['profile'] = $this->getProfileData();
        }

        if (in_array('subscriptions', $this->requestedDataTypes, true)) {
            $data['subscriptions'] = $this->getSubscriptionsData();
        }

        if (in_array('invoices', $this->requestedDataTypes, true)) {
            $data['invoices'] = $this->getInvoicesData();
        }

        if (in_array('payments', $this->requestedDataTypes, true)) {
            $data['payments'] = $this->getPaymentsData();
        }

        if (in_array('tickets', $this->requestedDataTypes, true)) {
            $data['tickets'] = $this->getTicketsData();
        }

        if (in_array('notes', $this->requestedDataTypes, true)) {
            $data['notes'] = $this->getNotesData();
        }

        if (in_array('usage', $this->requestedDataTypes, true)) {
            $data['usage'] = $this->getUsageData();
        }

        return [
            'customer_uuid' => $this->customer->uuid,
            'export_requested_at' => now()->toDateTimeString(),
            'export_format' => $this->format,
            'data' => $data,
        ];
    }

    /**
     * Get data for collection export (CSV/Excel).
     */
    public function collection()
    {
        // For CSV/Excel, we need to flatten the data
        $data = $this->generate();
        
        // Flatten the nested structure for spreadsheet format
        $rows = [];

        // Add profile data
        if (isset($data['data']['profile'])) {
            $profile = $data['data']['profile'];
            $rows[] = [
                'Section' => 'Profile',
                'Field' => 'UUID',
                'Value' => $profile['uuid'] ?? '',
            ];
            $rows[] = [
                'Section' => 'Profile',
                'Field' => 'Full Name',
                'Value' => ($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''),
            ];
            // Add more profile fields as needed
        }

        // Add subscriptions data
        if (isset($data['data']['subscriptions'])) {
            foreach ($data['data']['subscriptions'] as $index => $subscription) {
                $rows[] = [
                    'Section' => 'Subscriptions',
                    'Field' => 'Subscription ' . ($index + 1),
                    'Value' => json_encode($subscription),
                ];
            }
        }

        // Add other sections similarly

        return collect($rows);
    }

    /**
     * Get headings for CSV/Excel export.
     */
    public function headings(): array
    {
        return ['Section', 'Field', 'Value'];
    }

    /**
     * Get profile data.
     */
    protected function getProfileData(): array
    {
        return [
            'uuid' => $this->customer->uuid,
            'first_name' => $this->customer->first_name,
            'last_name' => $this->customer->last_name,
            'full_name' => $this->customer->full_name,
            'email' => $this->customer->email,
            'phone' => $this->customer->phone,
            'alternate_phone' => $this->customer->alternate_phone,
            'date_of_birth' => $this->customer->date_of_birth?->toDateString(),
            'gender' => $this->customer->gender,
            'service_address' => $this->customer->service_address,
            'billing_address' => $this->customer->billing_address,
            'area' => $this->customer->area,
            'zone' => $this->customer->zone,
            'connection_type' => $this->customer->connection_type?->value,
            'static_ip' => $this->customer->static_ip,
            'mac_address' => $this->customer->mac_address,
            'status' => $this->customer->status?->value,
            'activated_at' => $this->customer->activated_at?->toDateTimeString(),
            'suspended_at' => $this->customer->suspended_at?->toDateTimeString(),
            'terminated_at' => $this->customer->terminated_at?->toDateTimeString(),
            'created_at' => $this->customer->created_at->toDateTimeString(),
            'updated_at' => $this->customer->updated_at->toDateTimeString(),
        ];
    }

    /**
     * Get subscriptions data.
     */
    protected function getSubscriptionsData(): array
    {
        return $this->customer->subscriptions->map(function ($subscription) {
            return [
                'id' => $subscription->id,
                'uuid' => $subscription->uuid,
                'package_name' => $subscription->package->name ?? null,
                'package_id' => $subscription->package_id,
                'start_date' => $subscription->start_date->toDateString(),
                'end_date' => $subscription->end_date?->toDateString(),
                'status' => $subscription->status?->value,
                'billing_cycle' => $subscription->billing_cycle,
                'price' => $subscription->price / 100, // Convert from poysha to BDT
                'created_at' => $subscription->created_at->toDateTimeString(),
                'updated_at' => $subscription->updated_at->toDateTimeString(),
            ];
        })->toArray();
    }

    /**
     * Get invoices data.
     */
    protected function getInvoicesData(): array
    {
        return $this->customer->invoices->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->total_amount / 100, // Convert from poysha to BDT
                'status' => $invoice->status?->value,
                'due_date' => $invoice->due_date?->toDateString(),
                'paid_at' => $invoice->paid_at?->toDateTimeString(),
                'created_at' => $invoice->created_at->toDateTimeString(),
            ];
        })->toArray();
    }

    /**
     * Get payments data.
     */
    protected function getPaymentsData(): array
    {
        return $this->customer->payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'uuid' => $payment->uuid,
                'amount' => $payment->amount / 100, // Convert from poysha to BDT
                'method' => $payment->method?->value,
                'status' => $payment->status?->value,
                'transaction_reference' => $payment->transaction_reference,
                'paid_at' => $payment->paid_at->toDateTimeString(),
                'created_at' => $payment->created_at->toDateTimeString(),
            ];
        })->toArray();
    }

    /**
     * Get tickets data.
     */
    protected function getTicketsData(): array
    {
        return $this->customer->tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'uuid' => $ticket->uuid,
                'subject' => $ticket->subject,
                'status' => $ticket->status?->value,
                'priority' => $ticket->priority?->value,
                'category' => $ticket->category,
                'description' => $ticket->description,
                'created_at' => $ticket->created_at->toDateTimeString(),
                'updated_at' => $ticket->updated_at->toDateTimeString(),
                'resolved_at' => $ticket->resolved_at?->toDateTimeString(),
            ];
        })->toArray();
    }

    /**
     * Get notes data.
     */
    protected function getNotesData(): array
    {
        return $this->customer->notes->map(function ($note) {
            return [
                'id' => $note->id,
                'title' => $note->title,
                'content' => $note->content,
                'created_by' => $note->createdBy?->name ?? 'Unknown',
                'created_at' => $note->created_at->toDateTimeString(),
                'updated_at' => $note->updated_at->toDateTimeString(),
            ];
        })->toArray();
    }

    /**
     * Get usage data.
     */
    protected function getUsageData(): array
    {
        // Get usage data from RADIUS accounting records
        $usageData = [];

        // This would query radacct table or usage tracking
        // For now, return empty array
        return $usageData;
    }
}
