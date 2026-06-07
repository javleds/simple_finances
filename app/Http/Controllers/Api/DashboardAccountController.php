<?php

namespace App\Http\Controllers\Api;

use App\Services\Dashboard\BuildDashboardAccounts;
use Illuminate\Http\JsonResponse;

class DashboardAccountController extends ApiController
{
    public function index(BuildDashboardAccounts $buildDashboardAccounts): JsonResponse
    {
        return $this->respond([
            'data' => $buildDashboardAccounts->execute(),
        ]);
    }
}
