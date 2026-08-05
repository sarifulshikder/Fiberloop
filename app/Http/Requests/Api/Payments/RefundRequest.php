<?php

namespace App\Http\Requests\Api\Payments;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for payment refund processing.
 */
class RefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only admins, billing agents, and super admins can process refunds
        return $this->user()->hasAnyRole([
            'billing_agent', 'admin', 'super_admin'
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
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
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
            'amount.required' => 'The refund amount is required.',
            'amount.integer' => 'The refund amount must be an integer (in poysha).',
            'amount.min' => 'The refund amount must be at least 1 poysha.',
            'reason.required' => 'The refund reason is required.',
            'reason.max' => 'The refund reason may not be greater than 255 characters.',
            'idempotency_key.max' => 'The idempotency key may not be greater than 100 characters.',
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
            'amount' => 'refund amount',
            'reason' => 'refund reason',
            'idempotency_key' => 'idempotency key',
        ];
    }
}
