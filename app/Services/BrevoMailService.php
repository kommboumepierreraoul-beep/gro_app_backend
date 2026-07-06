<?php

namespace App\Services;

use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Configuration;
use Brevo\Client\Model\SendSmtpEmail;
use GuzzleHttp\Client;

class BrevoMailService
{
    protected TransactionalEmailsApi $api;

    public function __construct()
    {
        $config = Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', config('services.brevo.key'));

        $this->api = new TransactionalEmailsApi(
            new Client(),
            $config
        );
    }

    public function send(
        string $to,
        string $name,
        string $subject,
        string $html
    ): void {

        $email = new SendSmtpEmail([
            'sender' => [
                'email' => config('mail.from.address'),
                'name'  => config('mail.from.name'),
            ],
            'to' => [[
                'email' => $to,
                'name'  => $name,
            ]],
            'subject' => $subject,
            'htmlContent' => $html,
        ]);

        $this->api->sendTransacEmail($email);
    }
}
