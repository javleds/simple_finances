<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Legal;

use function abort_unless;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Legal\LegalAcceptanceRequest;
use App\Models\User;
use App\Services\Legal\GetLegalAcceptanceState;
use App\Services\Legal\RecordLegalAcceptance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalAcceptanceController extends ApiController
{
    public function show(Request $request, GetLegalAcceptanceState $state): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $this->respond(['data' => $state->execute($user)]);
    }

    public function store(LegalAcceptanceRequest $request, RecordLegalAcceptance $record, GetLegalAcceptanceState $state): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $record->execute($user, $request->acceptanceData());

        return $this->respond(['data' => $state->execute($user)]);
    }
}
