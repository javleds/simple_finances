<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

class GetAccountBalance extends FinanceTool
{
    protected string $name = 'get_account_balance';

    protected string $description = 'Read a visible account balance, and revalidate account access.';

    protected array $fields = ['account_id'];
}
