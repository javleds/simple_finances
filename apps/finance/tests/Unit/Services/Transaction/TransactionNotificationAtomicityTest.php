<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Transaction;

use function app;

use App\Dto\TransactionFormDto;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionUpdater;

use function config;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\NotificationType;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Notification;

use function now;

use RuntimeException;
use Tests\TestCase;

final class TransactionNotificationAtomicityTest extends TestCase
{
    use DatabaseTruncation;

    public function testNotificationFailureDoesNotUndoACommittedAccountMove(): void
    {
        config(['notifications.shared_transactions.mode' => 'immediate']);
        $actor = User::factory()->create();
        $recipient = User::factory()->create();
        $attributes = [
            'user_id'          => $actor->id,
            'balance'          => '0.00',
            'credit_card'      => false,
            'virtual'          => false,
            'spent'            => null,
            'available_credit' => null,
            'credit_line'      => null,
        ];
        $source = Account::factory()->create($attributes);
        $source->users()->sync([$actor->id => ['percentage' => '100.00']]);
        $destination = Account::factory()->create($attributes);
        $destination->users()->sync([
            $actor->id     => ['percentage' => '50.00'],
            $recipient->id => ['percentage' => '50.00'],
        ]);
        $notificationType = NotificationType::factory()->create(['name' => NotificationType::MOVEMENTS_NOTIFICATION]);
        $recipient->notificationTypes()->sync([$notificationType->id]);
        $recipient->notificableAccounts()->sync([$destination->id]);
        $this->actingAs($actor);
        $payload = [
            'account_id'   => $source->id,
            'type'         => 'income',
            'status'       => 'completed',
            'concept'      => 'Exact income',
            'amount'       => '100.01',
            'scheduled_at' => now(),
        ];
        $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray($payload));

        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Notification unavailable'));
        $payload['account_id'] = $destination->id;

        try {
            app(TransactionUpdater::class)->execute($transaction, TransactionFormDto::fromFormArray($payload));
            self::fail('The notification error should reach the caller.');
        } catch (RuntimeException $exception) {
            self::assertSame('Notification unavailable', $exception->getMessage());
        }

        self::assertSame($destination->id, $transaction->fresh()->account_id);
        self::assertSame('0.00', $source->fresh()->balance);
        self::assertSame('100.01', $destination->fresh()->balance);
        self::assertSame('100.01', $transaction->fresh()->ledgerEntries()->firstOrFail()->amount);
    }

    protected function tearDown(): void
    {
        try {
            $this->truncateTablesForAllConnections();
        } finally {
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
            parent::tearDown();
        }
    }
}
