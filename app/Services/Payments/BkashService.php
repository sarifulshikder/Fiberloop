<?php

namespace App\Services\Payments;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * bKash payment gateway service implementation.
 * Implements bKash Tokenized API v1.2.0
 * Documentation: https://developer.bka.sh/v1.2.0-beta/docs
 */
class BkashService extends BaseGatewayService implements PaymentGatewayContract
{
    protected string $gatewayName = 'bkash';

    protected string $accessToken = '';
    protected string $refreshToken = '';
    protected int $tokenExpiresAt = 0;

    public function __construct(IdempotencyService $idempotencyService)
    {
        parent::__construct($idempotencyService);
    }

    /**
     * Get or refresh the access token for bKash API.
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken && now()->timestamp < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        $this->refreshAccessToken();

        return $this->accessToken;
    }

    /**
     * Refresh the access token using grant token.
     */
    protected function refreshAccessToken(): void
    {
        $this->validateConfiguration(['app_key', 'app_secret', 'username', 'password']);

        $config = $this->config();

        // For bKash, we need to get a grant token first, then use it to get access token
        $grantToken = $this->getGrantToken();

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'username' => $config['username'],
            'password' => $config['password'],
            'app_key' => $config['app_key'],
            'app_secret' => $config['app_secret'],
        ])->withOptions(['verify' => false])
        ->timeout($this->getTimeout())
        ->post($this->getBaseUrl() . '/tokenized/check/grant', [
            'grant_token' => $grantToken,
            'app_key' => $config['app_key'],
            'app_secret' => $config['app_secret'],
        ]);

        if (!$response->successful()) {
            $this->logError('tokenized/check/grant', [], $response->status(), $response->body());
            throw new Exception('Failed to get bKash access token: ' . $response->body());
        }

        $data = $response->json();

        if (empty($data['id_token'])) {
            throw new Exception('Missing id_token in bKash token response');
        }

        // Now get the access token
        $accessResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'username' => $config['username'],
            'password' => $config['password'],
            'app_key' => $config['app_key'],
            'app_secret' => $config['app_secret'],
        ])->withOptions(['verify' => false])
        ->timeout($this->getTimeout())
        ->post($this->getBaseUrl() . '/tokenized/access', [
            'app_key' => $config['app_key'],
            'app_secret' => $config['app_secret'],
            'id_token' => $data['id_token'],
        ]);

        if (!$accessResponse->successful()) {
            $this->logError('tokenized/access', [], $accessResponse->status(), $accessResponse->body());
            throw new Exception('Failed to get bKash access token: ' . $accessResponse->body());
        }

        $accessData = $accessResponse->json();

        $this->accessToken = $accessData['access_token'] ?? '';
        $this->refreshToken = $accessData['refresh_token'] ?? '';
        $this->tokenExpiresAt = now()->timestamp + ($accessData['expires_in'] ?? 3600) - 60; // 1 minute buffer
    }

    /**
     * Get grant token from bKash.
     */
    protected function getGrantToken(): string
    {
        $this->validateConfiguration(['username', 'password']);

        $config = $this->config();

        $response = Http::withBasicAuth($config['username'], $config['password'])
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->withOptions(['verify' => false])
            ->timeout($this->getTimeout())
            ->post($this->getBaseUrl() . '/tokenized/grant', [
                'app_key' => $config['app_key'],
                'app_secret' => $config['app_secret'],
            ]);

        if (!$response->successful()) {
            $this->logError('tokenized/grant', [], $response->status(), $response->body());
            throw new Exception('Failed to get bKash grant token: ' . $response->body());
        }

        $data = $response->json();

        if (empty($data['grant_token'])) {
            throw new Exception('Missing grant_token in bKash response');
        }

        return $data['grant_token'];
    }

    /**
     * Initiate a bKash payment transaction.
     * Creates a payment request and returns the bkashURL for redirection.
     */
    public function initiatePayment(array $data): array
    {
        $this->validateConfiguration(['merchant_id']);

        $config = $this->config();
        $amount = $data['amount'] ?? 0; // Amount in poysha
        $invoiceNumber = $data['invoice_number'] ?? '';
        $customerName = $data['customer_name'] ?? '';
        $customerPhone = $data['customer_phone'] ?? '';
        $idempotencyKey = $data['idempotency_key'] ?? null;
        $callbackUrl = $data['callback_url'] ?? route('api.webhooks.bkash');

        // Use idempotency if key provided
        if ($idempotencyKey) {
            return $this->idempotencyService->execute($idempotencyKey, function () use ($data, $config, $amount, $invoiceNumber, $customerName, $customerPhone, $callbackUrl) {
                return $this->createPaymentRequest($config, $amount, $invoiceNumber, $customerName, $customerPhone, $callbackUrl);
            });
        }

        return $this->createPaymentRequest($config, $amount, $invoiceNumber, $customerName, $customerPhone, $callbackUrl);
    }

    /**
     * Create the actual payment request to bKash API.
     */
    protected function createPaymentRequest(
        array $config,
        int $amount,
        string $invoiceNumber,
        string $customerName,
        string $customerPhone,
        string $callbackUrl
    ): array {
        $accessToken = $this->getAccessToken();
        $amountBDT = $this->poyshaToBdt($amount);
        $orderId = 'ORDER_' . $invoiceNumber . '_' . now()->format('YmdHis');

        $paymentRequest = [
            'amount' => (float) $amountBDT,
            'merchantInvoiceNumber' => $invoiceNumber,
            'orderID' => $orderId,
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken,
            'X-APP-Key' => $config['app_key'],
        ])->withOptions(['verify' => false])
        ->timeout($this->getTimeout())
        ->post($this->getBaseUrl() . '/tokenized/create', $paymentRequest);

        if (!$response->successful()) {
            $this->logError('tokenized/create', $paymentRequest, $response->status(), $response->body());
            throw new Exception('Failed to create bKash payment: ' . $response->body());
        }

        $data = $response->json();

        if (empty($data['bkashURL'])) {
            throw new Exception('Missing bkashURL in bKash response');
        }

        return [
            'transaction_id' => $data['paymentID'] ?? $orderId,
            'gateway_reference' => $data['paymentID'] ?? null,
            'redirect_url' => $data['bkashURL'],
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
     * Verify a bKash payment transaction.
     */
    public function verifyPayment(string $transactionId): array
    {
        $accessToken = $this->getAccessToken();
        $config = $this->config();

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken,
            'X-APP-Key' => $config['app_key'],
        ])->withOptions(['verify' => false])
        ->timeout($this->getTimeout())
        ->post($this->getBaseUrl() . '/tokenized/query', [
            'paymentID' => $transactionId,
        ]);

        if (!$response->successful()) {
            $this->logError('tokenized/query', ['paymentID' => $transactionId], $response->status(), $response->body());
            throw new Exception('Failed to query bKash payment: ' . $response->body());
        }

        $data = $response->json();

        if (empty($data['status'])) {
            throw new Exception('Invalid response from bKash');
        }

        $status = $data['status'] ?? 'failed';
        $amountBDT = $data['amount'] ?? 0;
        $amountPoysha = $this->bdtToPoysha($amountBDT);

        return [
            'transaction_id' => $transactionId,
            'gateway_reference' => $data['paymentID'] ?? $transactionId,
            'status' => $status === 'COMPLETED' ? 'completed' : ($status === 'PENDING' ? 'pending' : 'failed'),
            'amount' => $amountPoysha,
            'paid_at' => $data['completedTime'] ?? now()->toDateTimeString(),
            'gateway_response' => json_encode($data),
        ];
    }

    /**
     * Handle webhook/callback from the bKash payment gateway.
     * bKash sends webhook with payment status updates.
     */
    public function handleWebhook(array $payload): array
    {
        // Verify webhook signature
        if (!$this->verifyWebhookSignature($payload)) {
            Log::warning('bKash webhook signature verification failed', [
                'payload' => $payload,
            ]);
            throw new Exception('Invalid webhook signature');
        }

        $paymentId = $payload['paymentID'] ?? '';
        $status = $payload['status'] ?? '';
        $amountBDT = $payload['amount'] ?? 0;

        // Verify the payment with bKash API to ensure it's legitimate
        $verification = $this->verifyPayment($paymentId);

        if ($verification['status'] !== 'completed') {
            return [
                'transaction_id' => $paymentId,
                'status' => 'failed',
                'amount' => $verification['amount'],
                'gateway_response' => json_encode($payload),
            ];
        }

        return [
            'transaction_id' => $paymentId,
            'gateway_reference' => $payload['paymentID'] ?? null,
            'status' => $status === 'COMPLETED' ? 'completed' : ($status === 'PENDING' ? 'pending' : 'failed'),
            'amount' => $verification['amount'],
            'paid_at' => $payload['completedTime'] ?? now()->toDateTimeString(),
            'gateway_response' => json_encode($payload),
        ];
    }

    /**
     * Verify webhook signature for bKash.
     * bKash uses HMAC-SHA256 with the webhook secret.
     */
    protected function verifyWebhookSignature(array $payload): bool
    {
        $secret = $this->getWebhookSecret();

        if (empty($secret)) {
            Log::warning('bKash webhook secret not configured');
            return false;
        }

        // bKash sends signature in header, but for webhook payload verification
        // we need to check the signature based on the payload
        // This is a placeholder - actual implementation depends on bKash webhook spec
        $providedSignature = $payload['signature'] ?? '';

        if (empty($providedSignature)) {
            return false;
        }

        // Generate expected signature
        // bKash webhook payload includes paymentID, status, amount, etc.
        $dataToSign = $this->getSignatureData($payload);
        $expectedSignature = hash_hmac('sha256', $dataToSign, $secret);

        return hash_equals($expectedSignature, $providedSignature);
    }

    /**
     * Extract data from payload for signature verification.
     */
    protected function getSignatureData(array $payload): string
    {
        // Order matters for signature verification
        $requiredKeys = ['paymentID', 'status', 'amount', 'merchantInvoiceNumber'];
        $data = [];

        foreach ($requiredKeys as $key) {
            $data[$key] = $payload[$key] ?? '';
        }

        return implode('|', $data);
    }

    /**
     * Refund a bKash payment transaction.
     */
    public function refund(string $transactionId, int $amount): array
    {
        $accessToken = $this->getAccessToken();
        $config = $this->config();
        $amountBDT = $this->poyshaToBdt($amount);

        $refundRequest = [
            'paymentID' => $transactionId,
            'amount' => (float) $amountBDT,
            'trxID' => 'REFUND_' . Str::upper(Str::random(8)),
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken,
            'X-APP-Key' => $config['app_key'],
        ])->withOptions(['verify' => false])
        ->timeout($this->getTimeout())
        ->post($this->getBaseUrl() . '/tokenized/refund', $refundRequest);

        if (!$response->successful()) {
            $this->logError('tokenized/refund', $refundRequest, $response->status(), $response->body());
            throw new Exception('Failed to refund bKash payment: ' . $response->body());
        }

        $data = $response->json();

        return [
            'refund_id' => $data['refundID'] ?? 'REFUND_' . Str::uuid(),
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'status' => $data['status'] ?? 'completed',
            'refunded_at' => now()->toDateTimeString(),
            'gateway_response' => json_encode($data),
        ];
    }
}
