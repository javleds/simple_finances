<?php

namespace App\Http\Controllers\Api;

use App\Services\Dashboard\BuildDashboardSubscriptions;
use Illuminate\Http\JsonResponse;

class DashboardSubscriptionController extends ApiController
{
    public function index(BuildDashboardSubscriptions $buildDashboardSubscriptions): JsonResponse
    {
        return $this->respond([
            'data' => $buildDashboardSubscriptions->execute(),
        ]);
    }
}
