<?php

namespace App\Http\Controllers\Api;

use App\Services\Dashboard\BuildDashboardGraph;
use Illuminate\Http\JsonResponse;

class DashboardGraphController extends ApiController
{
    public function index(BuildDashboardGraph $buildDashboardGraph): JsonResponse
    {
        return $this->respondCollection($buildDashboardGraph->execute());
    }
}
