<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Legal;

use App\Http\Controllers\Api\ApiController;
use App\Services\Legal\GetLegalDocuments;
use Illuminate\Http\JsonResponse;

class LegalDocumentsController extends ApiController
{
    public function show(GetLegalDocuments $documents): JsonResponse
    {
        return $this->respond(['data' => $documents->execute()]);
    }
}
