<?php

declare(strict_types=1);

use App\Http\Middleware\RequireExactMoneyContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

uses(Tests\TestCase::class);

it('blocks stale financial clients before calling the action', function (): void {
    $request = Request::create('/api/transactions', 'POST');
    $request->setRouteResolver(fn (): Route => new Route('POST', 'api/transactions', ['uses' => App\Http\Controllers\Api\TransactionController::class . '@store']));
    $response = (new RequireExactMoneyContract())->handle($request, function (): never {
        throw new RuntimeException('The financial action must not execute.');
    });
    expect($response->getStatusCode())->toBe(409);
});
