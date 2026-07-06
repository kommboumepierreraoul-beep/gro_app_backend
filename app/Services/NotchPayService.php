<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotchPayService
{
    public string $publicKey;
    public string $secretKey;
    public string $apiKey;
    public string $merchantId;
    public string $environment;
    public string $baseUrl;
    public string $webhookHash;

    public function __construct()
    {
        $this->publicKey = (string) config('notchpay.public_key', '');
        $this->secretKey = (string) config('notchpay.secret_key', '');
        $this->apiKey = (string) config('services.notchpay.api_key', $this->publicKey);
        $this->merchantId = env('NOTCHPAY_MERCHANT_ID', 'default_merchant');
        $this->environment = config('notchpay.sandbox', true) ? 'sandbox' : 'production';
        $this->baseUrl = rtrim((string) config('notchpay.endpoint', 'https://api.notchpay.co'), '/');
        $this->webhookHash = (string) config('notchpay.webhook_hash', '');
    }

    public function initiatePayment(array $data): array
    {
        $reference = $data['reference'] ?? 'PAY-' . strtoupper(uniqid());

        $payload = [
            'amount' => (int) $data['amount'],
            'currency' => $data['currency'] ?? 'XAF',
            'description' => $data['description'] ?? 'Paiement AgriPulse',
            'reference' => $reference,
            'merchant_reference' => $reference,
            'customer' => [
                'email' => $data['customer_email'] ?? '',
                'name' => $data['customer_name'] ?? 'Client',
                'phone' => $data['customer_phone'] ?? '',
            ],
            'callback_url' => $data['callback_url'] ?? config('notchpay.callback_url', config('app.url') . '/api/webhooks/notchpay'),
            'callback' => $data['callback_url'] ?? config('notchpay.callback_url', config('app.url') . '/api/webhooks/notchpay'),
            'return_url' => $data['return_url'] ?? config('notchpay.return_url', env('FRONTEND_URL', 'http://localhost:3000')),
        ];

        $response = Http::withHeaders($this->headers())
            ->post($this->baseUrl . '/payments', $payload);

        if ($response->successful()) {
            return $this->normalizePaymentResponse($response->json() ?? [], $reference);
        }

        Log::error('NotchPay initiatePayment error', [
            'status' => $response->status(),
            'body' => $response->body(),
            'reference' => $reference,
        ]);

        return [
            'error' => true,
            'message' => $response->body() ?: 'Erreur de paiement',
            'status' => $response->status(),
            'reference' => $reference,
        ];
    }

    public function createPayment(array $data): array
    {
        return $this->initiatePayment($data);
    }

    public function verifyPayment(string $reference): array
    {
        $response = Http::withHeaders($this->headers())
            ->get($this->baseUrl . '/payments/' . urlencode($reference));

        if ($response->successful()) {
            return $this->normalizePaymentResponse($response->json() ?? [], $reference);
        }

        Log::error('NotchPay verifyPayment error', [
            'reference' => $reference,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [
            'error' => true,
            'message' => $response->body() ?: 'Erreur de verification',
            'status' => $response->status(),
            'reference' => $reference,
        ];
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if ($this->webhookHash === '') {
            Log::warning('NotchPay webhook hash missing; signature verification skipped');
            return true;
        }

        if ($signature === '') {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookHash);
        return hash_equals($expectedSignature, $signature);
    }

    public function handleWebhook(array $payload): array
    {
        $data = $payload['data'] ?? $payload['transaction'] ?? $payload;

        return [
            'event' => $payload['event'] ?? $payload['type'] ?? null,
            'data' => $data,
            'status' => $this->normalizeStatus($data['status'] ?? $payload['status'] ?? null),
            'reference' => $this->extractReference($payload),
        ];
    }

    public function normalizeStatus(?string $status): ?string
    {
        return $status ? strtolower(trim($status)) : null;
    }

    public function isSuccessfulStatus(?string $status): bool
    {
        return in_array($this->normalizeStatus($status), [
            'complete',
            'completed',
            'success',
            'successful',
            'paid',
        ], true);
    }

    public function isFailedStatus(?string $status): bool
    {
        return in_array($this->normalizeStatus($status), [
            'failed',
            'failure',
            'cancelled',
            'canceled',
            'expired',
        ], true);
    }

    public function extractReference(array $payload): ?string
    {
        $data = $payload['data'] ?? $payload['transaction'] ?? $payload;

        return $data['merchant_reference']
            ?? $data['trxref']
            ?? $data['notchpay_trxref']
            ?? $data['reference']
            ?? $payload['merchant_reference']
            ?? $payload['trxref']
            ?? $payload['notchpay_trxref']
            ?? $payload['reference']
            ?? null;
    }

    public function getConfig(): array
    {
        return [
            'public_key' => $this->publicKey,
            'environment' => $this->environment,
            'base_url' => $this->baseUrl,
            'merchant_id' => $this->merchantId,
            'callback_url' => config('notchpay.callback_url'),
            'return_url' => config('notchpay.return_url'),
        ];
    }

    private function headers(): array
    {
        return [
            'Authorization' => $this->authorizationToken(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function authorizationToken(): string
    {
        return $this->apiKey ?: $this->publicKey ?: $this->secretKey;
    }

    private function normalizePaymentResponse(array $response, ?string $fallbackReference = null): array
    {
        $data = $response['data'] ?? $response['transaction'] ?? $response;
        $transaction = $response['transaction'] ?? $data;

        $authorizationUrl = $response['authorization_url']
            ?? $response['redirect_url']
            ?? $response['checkout_url']
            ?? $data['authorization_url']
            ?? $data['redirect_url']
            ?? $data['checkout_url']
            ?? null;

        $reference = $data['merchant_reference']
            ?? $data['reference']
            ?? $data['trxref']
            ?? $transaction['merchant_reference']
            ?? $transaction['reference']
            ?? $transaction['trxref']
            ?? $fallbackReference;

        $providerReference = $data['id']
            ?? $data['reference']
            ?? $data['trxref']
            ?? $transaction['id']
            ?? $transaction['reference']
            ?? $transaction['trxref']
            ?? null;

        return array_merge($response, [
            'authorization_url' => $authorizationUrl,
            'reference' => $reference,
            'provider_reference' => $providerReference,
            'merchant_reference' => $data['merchant_reference'] ?? $reference,
            'status' => $this->normalizeStatus($data['status'] ?? $transaction['status'] ?? $response['status'] ?? null),
            'transaction' => $transaction,
        ]);
    }
}
