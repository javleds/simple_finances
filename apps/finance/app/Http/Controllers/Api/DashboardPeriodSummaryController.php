<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Dashboard\PeriodSummaryRequest;
use App\Services\Dashboard\BuildDashboardPeriodSummary;
use Illuminate\Http\JsonResponse;

class DashboardPeriodSummaryController extends ApiController
{
    public function index(
        PeriodSummaryRequest $request,
        BuildDashboardPeriodSummary $buildDashboardPeriodSummary,
    ): JsonResponse {
        return $this->respond([
            'data' => $buildDashboardPeriodSummary->execute(
                $request->string('start_date')->toString(),
                $request->string('end_date')->toString(),
            ),
        ]);
    }
}
