<?php

use App\Enums\SharedTransactionNotificationAction;
use App\Models\Account;
use App\Models\NotificationType;
use App\Models\SharedTransactionNotificationBatch;
use App\Models\SharedTransactionNotificationItem;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use Illuminate\Support\Facades\Notification;

function accountMemberTransferNotificationHeaders(User $user): array
{
    $token = app(JwtTokenService::class)->generate($user)['token'];

    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ];
}

it('queues grouped shared movement notifications when a member reimbursement is paid', function () {
    config()->set('notifications.shared_transactions.mode', 'grouped');
    Notification::fake();

    $payer = User::factory()->create(['name' => 'Divanny']);
    $receiver = User::factory()->create(['name' => 'Javier']);
    $account = Account::factory()->create(['user_id' => $receiver->id]);
    $account->users()->sync([
        $payer->id => ['percentage' => 50],
        $receiver->id => ['percentage' => 50],
    ]);

    $notificationType = NotificationType::factory()->create([
        'name' => NotificationType::MOVEMENTS_NOTIFICATION,
    ]);
    $receiver->notificationTypes()->sync([$notificationType->id]);
    $receiver->notificableAccounts()->sync([$account->id]);

    $this
        ->withHeaders(accountMemberTransferNotificationHeaders($payer))
        ->postJson("/api/accounts/{$account->id}/member-transfers", [
            'from_user_id' => $payer->id,
            'to_user_id' => $receiver->id,
            'amount' => 250.0,
            'description' => 'Pago de pendientes de Divanny a Javier',
            'occurred_at' => '2026-07-20 10:00:00',
        ])
        ->assertCreated();

    $batch = SharedTransactionNotificationBatch::firstOrFail();
    $item = SharedTransactionNotificationItem::firstOrFail();

    expect($batch->user_id)->toBe($receiver->id)
        ->and($batch->group_key)->toBe("shared-movements:user:{$receiver->id}")
        ->and($item->account_id)->toBe($account->id)
        ->and($item->transaction_id)->toBeNull()
        ->and($item->modifier_id)->toBe($payer->id)
        ->and($item->action)->toBe(SharedTransactionNotificationAction::Settled)
        ->and($item->concept)->toBe('Pago de pendientes de Divanny a Javier')
        ->and($item->amount)->toBe(250.0);
});
