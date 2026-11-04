<?php

namespace App\Services\WhatsApp;

class IsWhatsAppConfigured
{
    public function execute(): bool
    {
        foreach (['phone_number_id', 'access_token', 'verify_token', 'app_secret', 'auth_template_name', 'auth_template_language'] as $key) {
            $value = config('services.whatsapp.'.$key);

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }
}
