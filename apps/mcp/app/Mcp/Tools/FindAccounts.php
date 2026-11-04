<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

class FindAccounts extends FinanceTool
{
    protected string $name = 'find_accounts';

    protected string $description = 'Find visible accounts by name. Return all candidates; never silently select ambiguous names.';

    protected array $fields = ['name', 'cursor', 'limit'];
}
