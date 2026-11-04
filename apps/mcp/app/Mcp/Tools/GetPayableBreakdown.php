<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

class GetPayableBreakdown extends FinanceTool
{
    protected string $name = 'get_payable_breakdown';

    protected string $description = 'Explain pending debt with transactions and explicit unattributed adjustments.';

    protected array $fields = ['account_id', 'cursor', 'limit'];
}
