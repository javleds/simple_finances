<?php

declare(strict_types=1);

namespace FinSi\FinanceDomain\Tests;

use function dirname;
use function file_get_contents;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ArchitectureTest extends TestCase
{
    public function testPackageHasNoFinanceRuntimeOrImplicitIdentityDependencies(): void
    {
        $source = dirname(__DIR__) . '/src';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $code = file_get_contents($file->getPathname());
            self::assertIsString($code);
            self::assertDoesNotMatchRegularExpression('/\\bApp\\\\|\\b(?:auth|request|route)\\s*\\(|Auth::|Request::/', $code, $file->getPathname());
        }

        self::assertDirectoryDoesNotExist($source . '/Actions');
    }
}
