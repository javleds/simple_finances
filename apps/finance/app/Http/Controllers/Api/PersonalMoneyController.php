<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Dto\PersonalMoneySettingsDto;
use App\Http\Requests\Api\PersonalMoneySettingsRequest;
use App\Http\Requests\Api\PersonalMoneySourceRequest;
use App\Services\PersonalMoney\BuildPersonalMoneySummary;
use App\Services\PersonalMoney\ResolvePersonalMoneySource;
use App\Services\PersonalMoney\UpdatePersonalMoneySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalMoneyController extends ApiController
{
    public function index(Request $request, BuildPersonalMoneySummary $summary): JsonResponse
    {
        $request->validate([
            'page'       => ['sometimes', 'integer', 'min:1'],
            'per_page'   => ['sometimes', 'integer', 'min:1', 'max:100'],
            'resolution' => ['sometimes', 'in:all,pending'],
        ]);

        return $this->respond(['data' => $summary->execute(
            $request->user()->id, $request->integer('page', 1),
            $request->integer('per_page', 20), (string) $request->input('resolution', 'all'),
        )]);
    }

    public function settings(
        PersonalMoneySettingsRequest $request,
        UpdatePersonalMoneySettings $updateSettings,
        BuildPersonalMoneySummary $summary,
    ): JsonResponse {
        $data = $request->validated();
        $updateSettings->execute($request->user()->id, new PersonalMoneySettingsDto(
            accountId: (int) $data['account_id'], startedAt: $data['started_at'] ?? null,
            openingBalance: (float) $data['opening_balance'], enabled: (bool) $data['enabled'],
            startMode: $data['start_mode'] ?? null, operationKey: $data['operation_key'] ?? null,
        ));

        return $this->respond(['data' => $summary->execute($request->user()->id)]);
    }

    public function resolve(
        PersonalMoneySourceRequest $request,
        ResolvePersonalMoneySource $resolveSource,
        BuildPersonalMoneySummary $summary,
    ): JsonResponse {
        $data = $request->validated();
        $resolveSource->execute($request->user()->id, $data['source_type'], (int) $data['source_id'], $data['resolution']);

        return $this->respond(['data' => $summary->execute($request->user()->id)]);
    }
}
