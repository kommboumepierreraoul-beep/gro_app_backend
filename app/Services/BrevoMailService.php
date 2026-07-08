<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use ReflectionClass;

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

    public function sendMailable(string $to, ?string $name, Mailable $mailable, ?string $subject = null): void
    {
        $this->send(
            $to,
            $name ?: $to,
            $subject ?: $this->resolveSubject($mailable),
            $mailable->render()
        );
    }

    private function resolveSubject(Mailable $mailable): string
    {
        try {
            $reflection = new ReflectionClass($mailable);

            if ($reflection->hasMethod('envelope') && $reflection->getMethod('envelope')->class === $reflection->getName()) {
                $subject = $mailable->envelope()->subject ?? null;

                if ($subject) {
                    return $subject;
                }
            }

            if ($reflection->hasMethod('build') && $reflection->getMethod('build')->class === $reflection->getName()) {
                $mailable->build();
            }

            $property = new \ReflectionProperty(Mailable::class, 'subject');
            $property->setAccessible(true);
            $subject = $property->getValue($mailable);

            if ($subject) {
                return $subject;
            }
        } catch (\Throwable) {
            // Fallback below.
        }

        return config('app.name', 'AgriPulse');
    }
}
