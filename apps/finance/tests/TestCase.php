<?php

declare(strict_types=1);

namespace Tests;

use function config;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['legal.terms_version' => '2026-10-18', 'legal.privacy_version' => '2026-10-18']);
        config(['legal.provider' => [
            'name'     => 'Test Provider',
            'rfc'      => 'TEST010101AAA',
            'address'  => 'Test address, México',
            'email'    => 'privacy@example.com',
            'phone'    => '+525500000000',
            'site_url' => 'https://example.com',
            'brand'    => 'Test Brand',
        ]]);
    }
}
