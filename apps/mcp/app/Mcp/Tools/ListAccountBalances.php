<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

class ListAccountBalances extends FinanceTool
{
    protected string $name = 'list_account_balances';

    protected string $description = 'List visible current account balances. Do not sum virtual accounts into consolidated money.';

    protected array $fields = ['cursor', 'limit', 'include_virtual'];
}
