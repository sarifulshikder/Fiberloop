<?php

namespace App\Services\Payments;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SSLCommerz payment gateway service implementation.
 * Implements SSLCommerz API v4
 * Documentation: https://developer.sslcommerz.com/
 */
class SSLCommerzService extends BaseGatewayService implements PaymentGatewayContract
{
    protected string $gatewayName = 'sslcommerz';

    public function __construct(IdempotencyService $idempotencyService)
    {
        parent::__construct($idempotencyService);
    }

    /**
     * Initiate an SSLCommerz payment transaction.
     * SSLCommerz uses a direct form submission approach with redirect.
     */
    public function initiatePayment(array $data): array
    {
        $this->validateConfiguration(['store_id', 'store_password']);

        $config = $this->config();
        $amount = $data['amount'] ?? 0; // Amount in poysha
        $invoiceNumber = $data['invoice_number'] ?? '';
        $customerName = $data['customer_name'] ?? '';
        $customerEmail = $data['customer_email'] ?? '';
        $customerPhone = $data['customer_phone'] ?? '';
        $idempotencyKey = $data['idempotency_key'] ?? null;
        $successUrl = $data['success_url'] ?? $config['success_url'];
        $failUrl = $data['fail_url'] ?? $config['fail_url'];
        $cancelUrl = $data['cancel_url'] ?? $config['cancel_url'];

        $amountBDT = $this->poyshaToBdt($amount);

        // Use idempotency if key provided
        if ($idempotencyKey) {
            return $this->idempotencyService->execute($idempotencyKey, function () use ($config, $amountBDT, $invoiceNumber, $customerName, $customerEmail, $customerPhone, $successUrl, $failUrl, $cancelUrl) {
                return $this->createPaymentRequest($config, $amountBDT, $invoiceNumber, $customerName, $customerEmail, $customerPhone, $successUrl, $failUrl, $cancelUrl);
            });
        }

        return $this->createPaymentRequest($config, $amountBDT, $invoiceNumber, $customerName, $customerEmail, $customerPhone, $successUrl, $failUrl, $cancelUrl);
    }

    /**
     * Create the payment request data for SSLCommerz.
     * SSLCommerz uses form-based submission, so we return the form data.
     */
    protected function createPaymentRequest(
        array $config,
        float $amountBDT,
        string $invoiceNumber,
        string $customerName,
        string $customerEmail,
        string $customerPhone,
        string $successUrl,
        string $failUrl,
        string $cancelUrl
    ): array {
        $isSandbox = $this->isSandbox();
        $baseUrl = $isSandbox ? $config['sandbox_base_url'] : $config['production_base_url'];

        $formData = [
            'store_id' => $config['store_id'],
            'store_passwd' => $config['store_password'],
            'total_amount' => (float) $amountBDT,
            'currency' => 'BDT',
            'tran_id' => $invoiceNumber,
            'success_url' => $successUrl,
            'fail_url' => $failUrl,
            'cancel_url' => $cancelUrl,
            'cus_name' => $customerName,
            'cus_email' => $customerEmail,
            'cus_phone' => $customerPhone,
            'cus_add1' => $data['customer_address'] ?? '',
            'cus_city' => $data['customer_city'] ?? '',
            'cus_postcode' => $data['customer_postcode'] ?? '',
            'cus_country' => $data['customer_country'] ?? 'Bangladesh',
            'shipping_method' => 'NO',
            'product_name' => $data['product_name'] ?? 'Fiberloop Service Payment',
            'product_category' => $data['product_category'] ?? 'Service',
            'product_profile' => $data['product_profile'] ?? 'service',
            'multi_card_name' => '',
            'value_a' => $data['value_a'] ?? '',
            'value_b' => $data['value_b'] ?? '',
            'value_c' => $data['value_c'] ?? '',
            'value_d' => $data['value_d'] ?? '',
            'opt_a' => $data['opt_a'] ?? '',
            'opt_b' => $data['opt_b'] ?? '',
            'opt_c' => $data['opt_c'] ?? '',
            'opt_d' => $data['opt_d'] ?? '',
        ];

        $redirectUrl = $baseUrl . '/gwprocess/v4/api.php?' . http_build_query($formData);

        return [
            'transaction_id' => $invoiceNumber,
            'gateway_reference' => $invoiceNumber,
            'redirect_url' => $redirectUrl,
            'form_data' => $formData,
            'amount' => $amountBDT,
            'currency' => 'BDT',
            'intent' => 'sale',
            'payer_name' => $customerName,
            'payer_email' => $customerEmail,
            'payer_phone' => $customerPhone,
            'status' => 'pending',
            'created_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Verify an SSLCommerz payment transaction.
     * SSLCommerz verification is done via IPN or validation API.
     */
    public function verifyPayment(string $transactionId): array
    {
        $this->validateConfiguration(['store_id', 'store_password']);

        $config = $this->config();
        $isSandbox = $this->isSandbox();
        $baseUrl = $isSandbox ? $config['sandbox_base_url'] : $config['production_base_url'];

        // SSLCommerz provides a validation API
        $validationUrl = $baseUrl . '/validator/api.php';

        $requestData = [
            'store_id' => $config['store_id'],
            'store_passwd' => $config['store_password'],
            'tran_id' => $transactionId,
            'v' => 1, // Validation version
        ];

        $response = Http::withOptions(['verify' => false])
            ->timeout($this->getTimeout())
            ->get($validationUrl, $requestData);

        if (!$response->successful()) {
            $this->logError('validator/api.php', ['tran_id' => $transactionId], $response->status(), $response->body());
            throw new Exception('Failed to verify SSLCommerz payment: ' . $response->body());
        }

        $data = $response->json();

        if (empty($data['status']) || empty($data['AMT'])) {
            throw new Exception('Invalid response from SSLCommerz validation');
        }

        $status = $data['status'] ?? 'failed';
        $amountBDT = $data['AMT'] ?? 0;
        $amountPoysha = $this->bdtToPoysha($amountBDT);

        return [
            'transaction_id' => $transactionId,
            'gateway_reference' => $data['tran_id'] ?? $transactionId,
            'status' => $status === 'VALID' ? 'completed' : ($status === 'PENDING' ? 'pending' : 'failed'),
            'amount' => $amountPoysha,
            'paid_at' => $data['tran_date'] ?? now()->toDateTimeString(),
            'gateway_response' => json_encode($data),
        ];
    }

    /**
     * Handle webhook/callback from the SSLCommerz payment gateway.
     * SSLCommerz sends IPN (Instant Payment Notification) to the configured URL.
     */
    public function handleWebhook(array $payload): array
    {
        // Verify webhook signature
        if (!$this->verifyWebhookSignature($payload)) {
            Log::warning('SSLCommerz webhook signature verification failed', [
                'payload' => $payload,
            ]);
            throw new Exception('Invalid webhook signature');
        }

        $tranId = $payload['tran_id'] ?? '';
        $status = $payload['status'] ?? '';
        $amountBDT = $payload['amount'] ?? 0;

        // Verify the payment with SSLCommerz API to ensure it's legitimate
        $verification = $this->verifyPayment($tranId);

        if ($verification['status'] !== 'completed') {
            return [
                'transaction_id' => $tranId,
                'status' => 'failed',
                'amount' => $verification['amount'],
                'gateway_response' => json_encode($payload),
            ];
        }

        return [
            'transaction_id' => $tranId,
            'gateway_reference' => $payload['tran_id'] ?? null,
            'status' => $status === 'VALID' ? 'completed' : ($status === 'PENDING' ? 'pending' : 'failed'),
            'amount' => $verification['amount'],
            'paid_at' => $payload['tran_date'] ?? now()->toDateTimeString(),
            'gateway_response' => json_encode($payload),
        ];
    }

    /**
     * Verify webhook signature for SSLCommerz.
     * SSLCommerz IPN includes a verification string that can be validated.
     */
    protected function verifyWebhookSignature(array $payload): bool
    {
        $secret = $this->getWebhookSecret();

        if (empty($secret)) {
            Log::warning('SSLCommerz webhook secret not configured');
            return false;
        }

        // SSLCommerz sends verify_sign and verify_key in IPN
        $verifySign = $payload['verify_sign'] ?? '';
        $verifyKey = $payload['verify_key'] ?? '';

        if (empty($verifySign) || empty($verifyKey)) {
            return false;
        }

        // Extract the fields used for signature verification
        // SSLCommerz concatenates specific fields in a particular order
        $dataToSign = $this->getWebhookSignatureData($payload);
        $expectedSign = hash_hmac('sha256', $dataToSign . $secret, $secret);

        return hash_equals($expectedSign, $verifySign);
    }

    /**
     * Extract data from webhook payload for signature verification.
     */
    protected function getWebhookSignatureData(array $payload): string
    {
        // SSLCommerz uses these fields for verification
        $requiredKeys = ['store_id', 'tran_id', 'amount', 'currency', 'status'];
        $data = [];

        foreach ($requiredKeys as $key) {
            $data[$key] = $payload[$key] ?? '';
        }

        return implode('|', $data);
    }

    /**
     * Refund an SSLCommerz payment transaction.
     * SSLCommerz refunds are processed through their merchant panel or API.
     */
    public function refund(string $transactionId, int $amount): array
    {
        $this->validateConfiguration(['store_id', 'store_password']);

        $config = $this->config();
        $isSandbox = $this->isSandbox();
        $baseUrl = $isSandbox ? $config['sandbox_base_url'] : $config['production_base_url'];
        $amountBDT = $this->poyshaToBdt($amount);

        // SSLCommerz refund API endpoint
        $refundUrl = $baseUrl . '/refund/api.php';

        $refundData = [
            'store_id' => $config['store_id'],
            'store_passwd' => $config['store_password'],
            'tran_id' => $transactionId,
            'refund_amount' => $amountBDT,
            'refund_remarks' => 'Customer request',
        ];

        $response = Http::withOptions(['verify' => false])
            ->timeout($this->getTimeout())
            ->post($refundUrl, $refundData);

        if (!$response->successful()) {
            $this->logError('refund/api.php', $refundData, $response->status(), $response->body());
            throw new Exception('Failed to refund SSLCommerz payment: ' . $response->body());
        }

        $data = $response->json();

        return [
            'refund_id' => $data['refund_id'] ?? 'REFUND_' . Str::uuid(),
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'status' => ($data['status'] ?? 'VALID') === 'VALID' ? 'completed' : 'failed',
            'refunded_at' => now()->toDateTimeString(),
            'gateway_response' => json_encode($data),
        ];
    }

    /**
     * Generate HTML form for SSLCommerz payment submission.
     * This is useful for direct form submission approach.
     */
    public function generatePaymentForm(array $data): string
    {
        $paymentData = $this->initiatePayment($data);
        $formData = $paymentData['form_data'] ?? [];

        $html = '<form id="sslcommerzForm" method="POST" action="' . $paymentData['redirect_url'] . '">';

        foreach ($formData as $key => $value) {
            $html .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
        }

        $html .= '<input type="submit" value="Pay with SSLCommerz">';
        $html .= '</form>';
        $html .= '<script>document.getElementById("sslcommerzForm").submit();</script>';

        return $html;
    }
}
