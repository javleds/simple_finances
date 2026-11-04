<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

class AggregateExpenseGroups extends FinanceTool
{
    protected string $name = 'aggregate_expense_groups';

    protected string $description = 'Calculate exact totals for inferred description groups; reject duplicates and unauthorized or out-of-period IDs.';

    protected array $fields = ['start_date', 'end_date', 'account_ids', 'category_ids', 'scope', 'groups'];
}
