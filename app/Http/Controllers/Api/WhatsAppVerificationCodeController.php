<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\WhatsApp\WhatsAppVerificationCodeRequest;
use App\Services\WhatsApp\GetWhatsAppConnection;
use App\Services\WhatsApp\RequestWhatsAppVerificationCode;
use Illuminate\Http\JsonResponse;

class WhatsAppVerificationCodeController extends ApiController
{
    public function store(WhatsAppVerificationCodeRequest $request, RequestWhatsAppVerificationCode $requestCode, GetWhatsAppConnection $getConnection): JsonResponse
    {
        $requestCode->execute($request->user(), $request->string('phone_number')->toString());

        return $this->respond(['data' => $getConnection->execute($request->user())], 201);
    }
}
