<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SendWhatsAppVerificationCode
{
    public function __construct(private readonly Factory $http)
    {
    }

    public function execute(string $phoneNumber, string $code): void
    {
        foreach (['phone_number_id', 'access_token', 'auth_template_name', 'auth_template_language'] as $key) {
            if (! is_string(config('services.whatsapp.'.$key)) || trim(config('services.whatsapp.'.$key)) === '') {
                throw new HttpException(503, 'WhatsApp no está configurado.');
            }
        }

        $url = 'https://graph.facebook.com/'.config('services.whatsapp.graph_version').'/'.config('services.whatsapp.phone_number_id').'/messages';

        try {
            $response = $this->http->withToken(config('services.whatsapp.access_token'))
                ->connectTimeout(5)->timeout(15)->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($phoneNumber, '+'),
                    'type' => 'template',
                    'template' => [
                        'name' => config('services.whatsapp.auth_template_name'),
                        'language' => ['code' => config('services.whatsapp.auth_template_language')],
                        'components' => [
                            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new HttpException(502, 'No se pudo enviar el código por WhatsApp. Intenta nuevamente.');
        }

        if (! $response->successful() || ! is_string($response->json('messages.0.id'))) {
            throw new HttpException(502, 'WhatsApp no aceptó el envío del código. Intenta nuevamente.');
        }
    }
}
