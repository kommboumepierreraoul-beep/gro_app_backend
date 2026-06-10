<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NotchPayService
{
    private string $publicKey;
    private string $secretKey;
    private string $endpoint;

    public function __construct()
    {
        $this->publicKey = config('services.notchpay.public_key');
        $this->secretKey = config('services.notchpay.secret_key');
        $this->endpoint  = config('services.notchpay.endpoint');
    }

    // Initier un paiement (dépôt)
    public function initiatePayment(array $data): array
    {
        $response = Http::withHeaders([
            'Authorization' => $this->publicKey,
            'Content-Type'  => 'application/json',
        ])->post("{$this->endpoint}/payments/initialize", [
            'amount'      => $data['amount'],
            'currency'    => $data['currency'] ?? 'XAF',
            'email'       => $data['email'],
            'reference'   => $data['reference'],
            'description' => $data['description'] ?? 'Dépôt wallet',
            'callback'    => $data['callback_url'] ?? config('app.url'),
        ]);

        return $response->json();
    }

    // Initier un transfert (retrait)
    public function initiateTransfer(array $data): array
    {
        $response = Http::withHeaders([
            'Authorization' => $this->secretKey,
            'Content-Type'  => 'application/json',
        ])->post("{$this->endpoint}/transfers", [
            'amount'      => $data['amount'],
            'currency'    => $data['currency'] ?? 'XAF',
            'beneficiary' => [
                'phone'   => $data['phone'],
                'channel' => $data['channel'], // 'cm.mtn' ou 'cm.orange'
            ],
            'reference'   => $data['reference'],
            'description' => $data['description'] ?? 'Retrait wallet',
        ]);

        return $response->json();
    }
    public function verifyPayment(string $reference): array
{
    $response = Http::withHeaders([
        'Authorization' => $this->publicKey,
        'Content-Type'  => 'application/json',
    ])->get("{$this->endpoint}/payments/{$reference}");

    return $response->json();
}
}
