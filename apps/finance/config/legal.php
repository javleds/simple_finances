<?php

declare(strict_types=1);

return [
    'provider' => [
        'name'     => env('LEGAL_PROVIDER_NAME'),
        'rfc'      => env('LEGAL_PROVIDER_RFC'),
        'address'  => env('LEGAL_PROVIDER_ADDRESS'),
        'email'    => env('LEGAL_PROVIDER_EMAIL'),
        'phone'    => env('LEGAL_PROVIDER_PHONE'),
        'site_url' => env('LEGAL_PROVIDER_SITE_URL'),
        'brand'    => env('LEGAL_PROVIDER_BRAND'),
    ],
    'terms_version'   => env('LEGAL_TERMS_VERSION', '2026-10-18'),
    'privacy_version' => env('LEGAL_PRIVACY_VERSION', '2026-10-18'),
];
