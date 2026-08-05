<?php

namespace App\Http\Requests\Api\Payments;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for manual/cash payment creation.
 */
class ManualPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only field agents, billing agents, and admins can record manual payments
        return $this->user()->hasAnyRole([
            'field_technician', 'billing_agent', 'admin', 'super_admin'
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'collection_date' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'receipt_number' => ['nullable', 'string', 'max:50'],
            'receipt_path' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_wallet_topup' => ['boolean'],
            'is_multi_invoice' => ['boolean'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'The customer ID is required.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'amount.required' => 'The payment amount is required.',
            'amount.integer' => 'The payment amount must be an integer (in poysha).',
            'amount.min' => 'The payment amount must be at least 1 poysha.',
            'invoice_id.exists' => 'The selected invoice does not exist.',
            'collection_date.date' => 'The collection date must be a valid date.',
            'location.max' => 'The location may not be greater than 255 characters.',
            'receipt_number.max' => 'The receipt number may not be greater than 50 characters.',
            'receipt_path.max' => 'The receipt path may not be greater than 255 characters.',
            'notes.max' => 'The notes may not be greater than 1000 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert amount from BDT to poysha if it's a float
        if (isset($this->amount) && is_float($this->amount)) {
            $this->merge(['amount' => (int) round($this->amount * 100)]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'invoice_id' => 'invoice',
            'amount' => 'payment amount',
            'collection_date' => 'collection date',
            'location' => 'collection location',
            'receipt_number' => 'receipt number',
            'receipt_path' => 'receipt image path',
            'notes' => 'notes',
        ];
    }
}
