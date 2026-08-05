<?php

return [
    /**
     * Default VAT/Tax rate in percentage.
     * Can be overridden per-tenant via the tax_rates table.
     * Bangladesh standard VAT is 15%.
     */
    'tax_rate' => env('BILLING_TAX_RATE', 15),

    /**
     * Grace period in days before applying late fees.
     * Customers have this many days after due date before late fees apply.
     */
    'grace_period_days' => (int) env('BILLING_GRACE_PERIOD_DAYS', 5),

    /**
     * Default late fee percentage.
     * Applied to outstanding amount after grace period.
     */
    'late_fee_percentage' => (int) env('BILLING_LATE_FEE_PERCENTAGE', 10),

    /**
     * Fixed late fee amount in poysha (BDT x 100).
     * If set to > 0, this overrides the percentage late fee.
     */
    'late_fee_fixed' => (int) env('BILLING_LATE_FEE_FIXED', 0),

    /**
     * Maximum late fee amount in poysha.
     * 0 = no limit.
     */
    'max_late_fee' => (int) env('BILLING_MAX_LATE_FEE', 0),

    /**
     * Dunning reminder schedule.
     * Days after grace period when reminders are sent.
     */
    'dunning_schedule' => [1, 3, 7, 14],

    /**
     * Currency settings.
     */
    'currency' => [
        'code' => 'BDT',
        'name' => 'Bangladeshi Taka',
        'subunit' => 'poysha',
        'subunit_value' => 100, // 1 BDT = 100 poysha
    ],

    /**
     * Invoice numbering settings.
     */
    'invoice_number' => [
        'prefix' => 'INV',
        'padding' => 8, // Zero-padding for sequence number
    ],

    /**
     * Billing cycle defaults.
     */
    'billing_cycles' => [
        'monthly' => [
            'name' => 'Monthly',
            'due_days' => 5,
        ],
        'quarterly' => [
            'name' => 'Quarterly',
            'due_days' => 7,
        ],
        'yearly' => [
            'name' => 'Yearly',
            'due_days' => 15,
        ],
    ],

    /**
     * Prepaid billing settings.
     */
    'prepaid' => [
        // Minimum balance required to start service (in poysha)
        'min_balance' => 0,
        
        // Auto-suspend when balance falls below this threshold (in poysha)
        'suspend_threshold' => 0,
    ],
];
