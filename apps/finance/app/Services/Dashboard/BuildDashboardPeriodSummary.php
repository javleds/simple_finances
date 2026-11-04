<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use FinSi\FinanceDomain\ReadModels\BuildDashboardPeriodSummary as SharedSummary;
use Illuminate\Contracts\Auth\Guard;

class BuildDashboardPeriodSummary
{
    public function __construct(private readonly Guard $auth, private readonly SharedSummary $summary)
    {
    }

    public function execute(string $startDate, string $endDate): array
    {
        return $this->summary->execute((int) $this->auth->id(), $startDate, $endDate);
    }
}
