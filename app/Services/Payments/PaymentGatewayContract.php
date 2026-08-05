<?php

namespace App\Services\Payments;

interface PaymentGatewayContract
{
    /**
     * Initiate a payment transaction
     *
     * @param array $data Payment data including amount, customer info, etc.
     * @return array Response with transaction ID, redirect URL, etc.
     */
    public function initiatePayment(array $data): array;

    /**
     * Verify a payment transaction
     *
     * @param string $transactionId The transaction ID from the gateway
     * @return array Verification result
     */
    public function verifyPayment(string $transactionId): array;

    /**
     * Handle webhook/callback from the payment gateway
     *
     * @param array $payload The webhook payload
     * @return array Processed payment data
     */
    public function handleWebhook(array $payload): array;

    /**
     * Refund a payment transaction
     *
     * @param string $transactionId The original transaction ID
     * @param float $amount Amount to refund (in poysha)
     * @return array Refund result
     */
    public function refund(string $transactionId, int $amount): array;
}
