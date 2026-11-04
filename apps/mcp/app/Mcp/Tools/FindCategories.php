<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

class FindCategories extends FinanceTool
{
    protected string $name = 'find_categories';

    protected string $description = 'Find authorized category candidates. An account selects its shared category catalog.';

    protected array $fields = ['name', 'account_id', 'cursor', 'limit'];
}
