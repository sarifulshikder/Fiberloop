<?php

namespace App\Http\Requests\Api\Payments;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for wallet top-up operations.
 */
class WalletTopUpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Customer can top up their own wallet, billing agents can top up any customer's wallet
        if ($this->user()->hasRole('customer')) {
            return $this->user()->customer !== null;
        }
        
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
            'amount' => ['required', 'integer', 'min:100'], // Minimum 1 BDT (100 poysha)
            'gateway' => [
                'required',
                Rule::in(PaymentMethod::values()),
            ],
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
            'amount.required' => 'The top-up amount is required.',
            'amount.integer' => 'The top-up amount must be an integer (in poysha).',
            'amount.min' => 'The minimum top-up amount is 1 BDT (100 poysha).',
            'gateway.required' => 'The payment gateway is required.',
            'gateway.in' => 'The selected payment gateway is invalid.',
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
            'amount' => 'top-up amount',
            'gateway' => 'payment gateway',
            'idempotency_key' => 'idempotency key',
        ];
    }
}
