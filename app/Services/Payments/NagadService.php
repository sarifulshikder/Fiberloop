<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Nagad payment gateway service implementation.
 * Implements Nagad Merchant API
 * Documentation: https://developer.nagad.com.pk/
 */
class NagadService extends BaseGatewayService implements PaymentGatewayContract
{
    protected string $gatewayName = 'nagad';
    
    protected string $sessionToken = '';
    protected int $sessionExpiresAt = 0;

    public function __construct(IdempotencyService $idempotencyService)
    {
        parent::__construct($idempotencyService);
    }

    /**
     * Get or refresh the session token for Nagad API.
     */
    protected function getSessionToken(): string
    {
        if ($this->sessionToken && now()->timestamp < $this->sessionExpiresAt) {
            return $this->sessionToken;
        }

        $this->refreshSessionToken();
        
        return $this->sessionToken;
    }

    /**
     * Refresh the session token.
     */
    protected function refreshSessionToken(): void
    {
        $this->validateConfiguration(['merchant_id', 'merchant_number', 'api_key', 'api_secret']);
        
        $config = $this->config();
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-KVP-Merchant-ID' => $config['merchant_id'],
            'X-KVP-Merchant-Number' => $config['merchant_number'],
        ])->withOptions(['verify' => false])
        ->timeout($this->getTimeout())
        ->post($this->getBaseUrl() . '/v2/checkout/session', [
            'merchantId' => $config['merchant_id'],
            'merchantNumber' => $config['merchant_number'],
        ]);

        if (!$response->successful()) {
            $this->logError('v2/checkout/session', [], $response->status(), $response->body());
            throw new Exception('Failed to get Nagad session token: ' . $response->body());
        }

        $data = $response->json();
        
        if (empty($data['session_token'])) {
            throw new Exception('Missing session_token in Nagad response');
        }

        $this->sessionToken = $data['session_token'];
        $this->sessionExpiresAt = now()->timestamp + ($data['expires_in'] ?? 3600) - 60;
    }

    /**
     * Generate signature for Nagad API requests.
     */
    protected function generateSignature(array $data): string
    {
        $this->validateConfiguration(['api_key', 'api_secret']);
        $config = $this->config();
        
        $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $jsonData, $config['api_secret']);
        
        return $signature;
    }

    /**
     * Initiate a Nagad payment transaction.
     */
    public function initiatePayment(array $data): array
    {
        $this->validateConfiguration(['merchant_id', 'merchant_number']);
        
        $config = $this->config();
        $amount = $data['amount'] ?? 0; // Amount in poysha
        $invoiceNumber = $data['invoice_number'] ?? '';
        $customerName = $data['customer_name'] ?? '';
        $customerPhone = $data['customer_phone'] ?? '';
        $idempotencyKey = $data['idempotency_key'] ?? null;
        $callbackUrl = $data['callback_url'] ?? route('api.webhooks.nagad');
        
        $amountBDT = $this->poyshaToBdt($amount);
        $orderId = 'ORDER_' . $invoiceNumber . '_' . now()->format('YmdHis');
        
        // Use idempotency if key provided
        if ($idempotencyKey) {
            return $this->idempotencyService->execute($idempotencyKey, function () use ($data, $config, $amount, $amountBDT, $invoiceNumber, $customerName, $customerPhone, $callbackUrl, $orderId) {
                return $this->createPaymentRequest($config, $amountBDT, $invoiceNumber, $customerName, $customerPhone, $callbackUrl, $orderId);
            });
        }
        
        return $this->createPaymentRequest($config, $amountBDT, $invoiceNumber, $customerName, $customerPhone, $callbackUrl, $orderId);
    }

    /**
     * Create the actual payment request to Nagad API.
     */
    protected function createPaymentRequest(
        array $config,
        float $amountBDT,
        string $invoiceNumber,
        string $customerName,
        string $customerPhone,
        string $callbackUrl,
        string $orderId
    ): array {
        $sessionToken = $this->getSessionToken();
        
        $paymentRequest = [
            'merchantId' => $config['merchant_id'],
            'orderId' => $orderId,
            'amount' => $amountBDT,
            'currencyCode' => '050', // BDT currency code
            'merchantInvoiceNumber' => $invoiceNumber,
            'customerName' => $customerName,
            'customerPhone' => $customerPhone,
            'callbackURL' => $callbackUrl,
        ];

        $signature = $this->generateSignature($paymentRequest);
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-KVP-Merchant-ID' => $config['merchant_id'],
            'X-KVP-Merchant-Number' => $config['merchant_number'],
            'X-KVP-Session-Token' => $sessionToken,
            'X-KVP-Signature' => $signature,
        ])->withOptions(['verify' => false])
        ->timeout($this->getTimeout())
        ->post($this->getBaseUrl() . '/v2/create', $paymentRequest);

        if (!$response->successful()) {
            $this->logError('v2/create', $paymentRequest, $response->status(), $response->body());
            throw new Exception('Failed to create Nagad payment: ' . $response->body());
        }

        $data = $response->json();
        
        if (empty($data['paymentReference'])) {
            throw new Exception('Missing paymentReference in Nagad response');
        }

        return [
            'transaction_id' => $data['paymentReference'],
            'gateway_reference' => $data['paymentReference'],
            'redirect_url' => $data['redirectURL'] ?? '',
            'amount' => $amountBDT,
            'currency' => 'BDT',
            'intent' => 'sale',
            'payer_name' => $customerName,
            'payer_phone' => $customerPhone,
            'order_id' => $orderId,
            'status' => 'pending',
            'created_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Verify a Nagad payment transaction.
     */
    public function verifyPayment(string $transactionId): array
    {
        $this->validateConfiguration(['merchant_id', 'merchant_number']);
        
        $config = $this->config();
        $sessionToken = $this->getSessionToken();
        
        $requestData = [
            'merchantId' => $config['merchant_id'],
            'paymentReference' => $transactionId,
        ];
        
        $signature = $this->generateSignature($requestData);
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-KVP-Merchant-ID' => $config['merchant_id'],
            'X-KVP-Merchant-Number' => $config['merchant_number'],
            'X-KVP-Session-Token' => $sessionToken,
            'X-KVP-Signature' => $signature,
        ])->withOptions(['verify' => false])
        ->timeout($this->getTimeout())
        ->get($this->getBaseUrl() . '/v2/verify', $requestData);

        if (!$response->successful()) {
            $this->logError('v2/verify', ['paymentReference' => $transactionId], $response->status(), $response->body());
            throw new Exception('Failed to verify Nagad payment: ' . $response->body());
        }

        $data = $response->json();
        
        if (empty($data['status'])) {
            throw new Exception('Invalid response from Nagad');
        }

        $status = $data['status'] ?? 'failed';
        $amountBDT = $data['amount'] ?? 0;
        $amountPoysha = $this->bdtToPoysha($amountBDT);
        
        return [
            'transaction_id' => $transactionId,
            'gateway_reference' => $data['paymentReference'] ?? $transactionId,
            'status' => $status === 'SUCCESS' ? 'completed' : ($status === 'PENDING' ? 'pending' : 'failed'),
            'amount' => $amountPoysha,
            'paid_at' => $data['completedTime'] ?? now()->toDateTimeString(),
            'gateway_response' => json_encode($data),
        ];
    }

    /**
     * Handle webhook/callback from the Nagad payment gateway.
     */
    public function handleWebhook(array $payload): array
    {
        // Verify webhook signature
        if (!$this->verifyWebhookSignature($payload)) {
            Log::warning('Nagad webhook signature verification failed', [
                'payload' => $payload,
            ]);
            throw new Exception('Invalid webhook signature');
        }

        $paymentReference = $payload['paymentReference'] ?? '';
        $status = $payload['status'] ?? '';
        $merchantInvoiceNumber = $payload['merchantInvoiceNumber'] ?? '';
        $amountBDT = $payload['amount'] ?? 0;
        
        // Verify the payment with Nagad API to ensure it's legitimate
        $verification = $this->verifyPayment($paymentReference);
        
        if ($verification['status'] !== 'completed') {
            return [
                'transaction_id' => $paymentReference,
                'status' => 'failed',
                'amount' => $verification['amount'],
                'gateway_response' => json_encode($payload),
            ];
        }

        return [
            'transaction_id' => $paymentReference,
            'gateway_reference' => $payload['paymentReference'] ?? null,
            'status' => $status === 'SUCCESS' ? 'completed' : ($status === 'PENDING' ? 'pending' : 'failed'),
            'amount' => $verification['amount'],
            'paid_at' => $payload['completedTime'] ?? now()->toDateTimeString(),
            'gateway_response' => json_encode($payload),
        ];
    }

    /**
     * Verify webhook signature for Nagad.
     * Nagad uses HMAC-SHA256 with the API secret.
     */
    protected function verifyWebhookSignature(array $payload): bool
    {
        $secret = $this->getWebhookSecret();
        
        if (empty($secret)) {
            Log::warning('Nagad webhook secret not configured');
            return false;
        }

        $providedSignature = $payload['signature'] ?? '';
        
        if (empty($providedSignature)) {
            return false;
        }

        // Extract the fields used for signature (order matters)
        $dataToSign = $this->getWebhookSignatureData($payload);
        $expectedSignature = hash_hmac('sha256', $dataToSign, $secret);
        
        return hash_equals($expectedSignature, $providedSignature);
    }

    /**
     * Extract data from webhook payload for signature verification.
     */
    protected function getWebhookSignatureData(array $payload): string
    {
        $requiredKeys = ['merchantId', 'paymentReference', 'status', 'amount', 'merchantInvoiceNumber'];
        $data = [];
        
        foreach ($requiredKeys as $key) {
            $data[$key] = $payload[$key] ?? '';
        }
        
        return implode('|', $data);
    }

    /**
     * Refund a Nagad payment transaction.
     */
    public function refund(string $transactionId, int $amount): array
    {
        $this->validateConfiguration(['merchant_id', 'merchant_number']);
        
        $config = $this->config();
        $sessionToken = $this->getSessionToken();
        $amountBDT = $this->poyshaToBdt($amount);
        
        $refundRequest = [
            'merchantId' => $config['merchant_id'],
            'paymentReference' => $transactionId,
            'amount' => $amountBDT,
            'refundReason' => 'Customer request',
        ];
        
        $signature = $this->generateSignature($refundRequest);
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-KVP-Merchant-ID' => $config['merchant_id'],
            'X-KVP-Merchant-Number' => $config['merchant_number'],
            'X-KVP-Session-Token' => $sessionToken,
            'X-KVP-Signature' => $signature,
        ])->withOptions(['verify' => false])
        ->timeout($this->getTimeout())
        ->post($this->getBaseUrl() . '/v2/refund', $refundRequest);

        if (!$response->successful()) {
            $this->logError('v2/refund', $refundRequest, $response->status(), $response->body());
            throw new Exception('Failed to refund Nagad payment: ' . $response->body());
        }

        $data = $response->json();
        
        return [
            'refund_id' => $data['refundId'] ?? 'REFUND_' . Str::uuid(),
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'status' => $data['status'] ?? 'completed',
            'refunded_at' => now()->toDateTimeString(),
            'gateway_response' => json_encode($data),
        ];
    }
}
