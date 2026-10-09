<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\WhatsApp\WhatsAppConnectionRequest;
use App\Services\WhatsApp\ConfirmWhatsAppConnection;
use App\Services\WhatsApp\DisconnectWhatsAppConnection;
use App\Services\WhatsApp\GetWhatsAppConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppConnectionController extends ApiController
{
    public function show(Request $request, GetWhatsAppConnection $getConnection): JsonResponse
    {
        return $this->respond(['data' => $getConnection->execute($request->user())]);
    }

    public function store(WhatsAppConnectionRequest $request, ConfirmWhatsAppConnection $confirm, GetWhatsAppConnection $getConnection): JsonResponse
    {
        $confirm->execute($request->user(), $request->string('code')->toString());

        return $this->respond(['data' => $getConnection->execute($request->user())]);
    }

    public function delete(Request $request, DisconnectWhatsAppConnection $disconnect, GetWhatsAppConnection $getConnection): JsonResponse
    {
        $disconnect->execute($request->user());

        return $this->respond(['data' => $getConnection->execute($request->user())]);
    }
}
