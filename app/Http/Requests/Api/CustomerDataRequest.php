<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'data_types' => [
                'sometimes',
                'array',
            ],
            'data_types.*' => [
                'string',
                Rule::in(['profile', 'subscriptions', 'invoices', 'payments', 'tickets', 'notes', 'kyc', 'usage']),
            ],
            'format' => [
                'sometimes',
                'string',
                Rule::in(['json', 'csv', 'xlsx']),
            ],
            'scope' => [
                'sometimes',
                'string',
                Rule::in(['all', 'specific']),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'data_types.*.in' => 'The data type :value is not supported for export.',
            'format.in' => 'The format :value is not supported. Supported formats are: json, csv, xlsx.',
            'scope.in' => 'The scope :value is not valid. Must be "all" or "specific".',
        ];
    }
}
