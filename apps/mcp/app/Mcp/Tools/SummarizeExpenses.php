<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

class SummarizeExpenses extends FinanceTool
{
    protected string $name = 'summarize_expenses';

    protected string $description = 'Aggregate completed root expenses over the entire filter. Global totals exclude virtual accounts. Only account_total is supported.';

    protected array $fields = ['start_date', 'end_date', 'account_ids', 'category_ids', 'scope', 'group_by', 'cursor', 'limit'];
}
