<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $application = parent::createApplication();
        $connection = config('database.default');
        if (config('database.connections.'.$connection.'.driver') === 'mysql'
            && ! str_ends_with((string) config('database.connections.'.$connection.'.database'), '_test')) {
            throw new \RuntimeException('MySQL test suites require a disposable database ending in _test.');
        }

        return $application;
    }

}
