<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrevoMailService
{
    public function send(string $to, string $name, string $subject, string $html): void
    {
        $response = Http::withHeaders([
            'api-key' => config('services.brevo.key'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender' => [
                'name' => config('mail.from.name'),
                'email' => config('mail.from.address'),
            ],
            'to' => [[
                'email' => $to,
                'name' => $name,
            ]],
            'subject' => $subject,
            'htmlContent' => $html,
        ]);

        if ($response->failed()) {
            throw new \Exception('Erreur Brevo: ' . $response->body());
        }
    }
}
