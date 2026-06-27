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
        $this->publicKey = env('NOTCHPAY_PUBLIC_KEY', '');
        $this->secretKey = env('NOTCHPAY_SECRET_KEY', '');
        $this->apiKey = env('NOTCHPAY_API_KEY', env('NOTCHPAY_PUBLIC_KEY', ''));
        $this->merchantId = env('NOTCHPAY_MERCHANT_ID', 'default_merchant');
        $this->environment = env('NOTCHPAY_SANDBOX', true) ? 'sandbox' : 'production';
        $this->baseUrl = env('NOTCHPAY_ENDPOINT', 'https://api.notchpay.co');
        $this->webhookHash = env('NOTCHPAY_WEBHOOK_HASH', '');
    }
 
    /**
     * Initier un paiement (pour WalletController)
     */
    public function initiatePayment(array $data): array
    {
        $payload = [
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'XAF',
            'description' => $data['description'] ?? 'Dépôt wallet AgriPulse',
            'reference' => $data['reference'] ?? 'DEP-' . uniqid(),
            'customer' => [
                'email' => $data['customer_email'] ?? '',
                'name' => $data['customer_name'] ?? 'Client',
                'phone' => $data['customer_phone'] ?? '',
            ],
            'callback_url' => $data['callback_url'] ?? env('NOTCHPAY_CALLBACK_URL', config('app.url') . '/api/wallet/deposit/callback'),
            'return_url' => $data['return_url'] ?? env('NOTCHPAY_RETURN_URL', env('FRONTEND_URL', 'http://localhost:3000') . '/wallet'),
        ];
 
        $response = Http::withHeaders([
            'Authorization' => $this->publicKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($this->baseUrl . '/payments', $payload);
 
        if ($response->successful()) {
            return $response->json();
        }
 
        Log::error('NotchPay initiatePayment error', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
 
        return [
            'error' => true,
            'message' => $response->body() ?? 'Erreur de paiement',
            'status' => $response->status(),
        ];
    }
 
    /**
     * Créer un paiement (alias pour initiatePayment)
     */
    public function createPayment(array $data): array
    {
        return $this->initiatePayment($data);
    }
 
    /**
     * Vérifier le statut d'un paiement
     */
    public function verifyPayment(string $reference): array
    {
        $response = Http::withHeaders([
            'Authorization' => $this->publicKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get($this->baseUrl . '/payments/' . $reference);
 
        if ($response->successful()) {
            return $response->json();
        }
 
        Log::error('NotchPay verifyPayment error', [
            'reference' => $reference,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
 
        return [
            'error' => true,
            'message' => $response->body() ?? 'Erreur de vérification',
            'status' => $response->status(),
        ];
    }
 
    /**
     * Vérifier la signature du webhook
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookHash);
        return hash_equals($expectedSignature, $signature);
    }
 
    /**
     * Traiter un événement webhook
     */
    public function handleWebhook(array $payload): array
    {
        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];
 
        if (!$event) {
            return ['error' => true, 'message' => 'Événement non spécifié'];
        }
 
        return [
            'event' => $event,
            'data' => $data,
            'status' => $data['status'] ?? null,
            'reference' => $data['reference'] ?? null,
        ];
    }
 
    /**
     * Récupérer la configuration
     */
    public function getConfig(): array
    {
        return [
            'public_key' => $this->publicKey,
            'environment' => $this->environment,
            'base_url' => $this->baseUrl,
            'merchant_id' => $this->merchantId,
        ];
    }
}
 