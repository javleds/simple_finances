<?php

namespace App\Http\Controllers\Api;

use App\Services\VirtualAccounts\BuildVirtualAccountSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VirtualAccountController extends ApiController
{
    public function index(Request $request, BuildVirtualAccountSummary $buildSummary): JsonResponse
    {
        return $this->respond([
            'data' => $buildSummary->execute($request->user()->id),
        ]);
    }
}
