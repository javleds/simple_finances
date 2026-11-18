<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/check-production.php';

final class ProductionDatabaseGrantsTest extends TestCase
{
    #[DataProvider('grants')]
    public function testRejectsWritePrivilegesRolesAndDelegation(array $grants, bool $expected): void
    {
        self::assertSame($expected, \hasOnlyReadPrivileges($grants));
    }

    public static function grants(): array
    {
        return [
            [['GRANT USAGE ON *.* TO reader', 'GRANT SELECT ON finance.* TO reader'], true],
            [['GRANT SELECT, INSERT ON finance.* TO reader'], false],
            [['GRANT ALL PRIVILEGES ON *.* TO reader'], false],
            [['GRANT SELECT ON finance.* TO reader WITH GRANT OPTION'], false],
            [['GRANT finance_role TO reader'], false],
            [['GRANT FILE ON *.* TO reader'], false],
            [[], false],
        ];
    }
}
