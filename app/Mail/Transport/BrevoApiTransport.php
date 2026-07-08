<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class BrevoApiTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (!$email instanceof Email) {
            throw new TransportException('Brevo API only supports Symfony Email messages.');
        }

        $apiKey = config('services.brevo.key');

        if (!$apiKey) {
            throw new TransportException('BREVO_API_KEY is not configured.');
        }

        $payload = [
            'sender' => $this->formatAddress($email->getFrom()[0] ?? new Address(config('mail.from.address'), config('mail.from.name'))),
            'to' => array_map(fn (Address $address) => $this->formatAddress($address), $email->getTo()),
            'subject' => $email->getSubject() ?: config('app.name', 'AgriPulse'),
        ];

        if ($email->getHtmlBody()) {
            $payload['htmlContent'] = $email->getHtmlBody();
        }

        if ($email->getTextBody()) {
            $payload['textContent'] = $email->getTextBody();
        }

        if (empty($payload['htmlContent']) && empty($payload['textContent'])) {
            $payload['textContent'] = $email->toString();
        }

        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', $payload);

        if ($response->failed()) {
            throw new TransportException('Brevo API error: ' . $response->body(), $response->status());
        }
    }

    public function __toString(): string
    {
        return 'brevo-api';
    }

    private function formatAddress(Address $address): array
    {
        return [
            'email' => $address->getAddress(),
            'name' => $address->getName() ?: $address->getAddress(),
        ];
    }
}
