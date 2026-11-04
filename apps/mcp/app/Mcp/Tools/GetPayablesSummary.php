<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

class GetPayablesSummary extends FinanceTool
{
    protected string $name = 'get_payables_summary';

    protected string $description = 'Read amounts the actor owes, matching the Finance dashboard pending reimbursements.';

    protected array $fields = ['account_id'];
}
