<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateServiceActor;
use App\Mcp\FinanceServer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', FinanceServer::class)->middleware(AuthenticateServiceActor::class);
Route::get('/health/ready', function () {
    abort_unless(strlen((string) config('mcp_service.token')) >= 32 && config('mcp_service.terms_hash') && config('mcp_service.privacy_hash'), 503);
    DB::select('SELECT 1');

    return response()->json(['status' => 'ready']);
});
