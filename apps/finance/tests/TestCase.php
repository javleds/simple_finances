<?php

declare(strict_types=1);

namespace Tests;

use function config;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

use function str_ends_with;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $application = parent::createApplication();
        $connection = config('database.default');
        if ('mysql' === config('database.connections.' . $connection . '.driver')
            && ! str_ends_with((string) config('database.connections.' . $connection . '.database'), '_test')) {
            throw new RuntimeException('MySQL test suites require a disposable database ending in _test.');
        }

        return $application;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('X-Finance-Contract', '2');
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
