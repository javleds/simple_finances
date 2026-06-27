<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Auth\JwtTokenService;

function dashboardPeriodSummaryHeaders(User $user): array
{
    $token = app(JwtTokenService::class)->generate($user)['token'];

    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ];
}

it('returns the dashboard period summary through the API', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id);

    Transaction::factory()->income()->completed()->create([
        'amount' => 45000,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-05',
    ]);
    Transaction::factory()->outcome()->completed()->create([
        'amount' => 2500,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-20',
    ]);

    $this
        ->withHeaders(dashboardPeriodSummaryHeaders($user))
        ->getJson('/api/dashboard/period-summary?start_date=2026-06-01&end_date=2026-06-30')
        ->assertOk()
        ->assertJsonPath('data.period.start_date', '2026-06-01')
        ->assertJsonPath('data.period.end_date', '2026-06-30')
        ->assertJsonPath('data.income_total', 45000.0)
        ->assertJsonPath('data.outcome_total', 2500.0)
        ->assertJsonPath('data.balance', 42500.0);
});

it('validates the dashboard period summary date range', function () {
    $user = User::factory()->create();

    $this
        ->withHeaders(dashboardPeriodSummaryHeaders($user))
        ->getJson('/api/dashboard/period-summary?start_date=2026-06-30&end_date=2026-06-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});
