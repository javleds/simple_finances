<?php

declare(strict_types=1);

namespace Database\Factories\Scenarios;

use function app;
use function collect;
use function fake;

use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

use function range;
use function sprintf;

class UserScenarioFactory
{
    public static function for(User $user): self
    {
        return new self($user);
    }

    public function individualAccounts(int $count): self
    {
        foreach (range(1, $count) as $index) {
            $account = Account::factory()->createQuietly([
                'user_id'          => $this->subject->id,
                'name'             => sprintf('%s Personal Account %d', $this->subject->name, $index),
                'balance'          => 0,
                'credit_card'      => false,
                'credit_line'      => null,
                'cutoff_day'       => null,
                'next_cutoff_date' => null,
                'available_credit' => null,
                'spent'            => null,
            ]);

            $account->users()->syncWithoutDetaching([
                $this->subject->id => ['percentage' => 100.0],
            ]);

            $this->generatedAccounts->push(new GeneratedAccountContext(
                account: $account,
                owner: $this->subject,
                members: collect([$this->subject]),
                ownedBySubject: true,
            ));
        }

        return $this;
    }

    public function sharedOwnedAccounts(int $count): self
    {
        foreach (range(1, $count) as $index) {
            $account = Account::factory()->createQuietly([
                'user_id'          => $this->subject->id,
                'name'             => sprintf('%s Shared Account %d', $this->subject->name, $index),
                'balance'          => 0,
                'credit_card'      => false,
                'credit_line'      => null,
                'cutoff_day'       => null,
                'next_cutoff_date' => null,
                'available_credit' => null,
                'spent'            => null,
            ]);

            $account->users()->syncWithoutDetaching([
                $this->subject->id => ['percentage' => 100.0],
            ]);

            $this->generatedAccounts->push(new GeneratedAccountContext(
                account: $account,
                owner: $this->subject,
                members: collect([$this->subject]),
                ownedBySubject: true,
            ));
        }

        return $this;
    }

    public function sharedInvitedAccounts(int $count): self
    {
        foreach (range(1, $count) as $index) {
            $owner = User::factory()->createQuietly();
            $account = Account::factory()->createQuietly([
                'user_id'          => $owner->id,
                'name'             => sprintf('%s Invited Account %d', $this->subject->name, $index),
                'balance'          => 0,
                'credit_card'      => false,
                'credit_line'      => null,
                'cutoff_day'       => null,
                'next_cutoff_date' => null,
                'available_credit' => null,
                'spent'            => null,
            ]);

            $account->users()->syncWithoutDetaching([
                $owner->id => ['percentage' => 100.0],
            ]);

            $this->createAcceptedInvite($account, $owner, $this->subject);

            $this->generatedAccounts->push(new GeneratedAccountContext(
                account: $account,
                owner: $owner,
                members: collect([$owner, $this->subject]),
                ownedBySubject: false,
            ));
        }

        $this->rebalanceSelectedAccountMemberships();

        return $this;
    }

    public function withUsers(int $count): self
    {
        foreach ($this->generatedAccounts as $context) {
            foreach (range(1, $count) as $index) {
                $user = User::factory()->createQuietly();

                $this->createAcceptedInvite($context->account, $context->owner, $user);
                $context->addMember($user);
            }
        }

        $this->rebalanceSelectedAccountMemberships();

        return $this;
    }

    public function withMixedTransactions(int $count): self
    {
        foreach ($this->generatedAccounts as $context) {
            $runningBalance = '0.00';

            foreach (range(1, $count) as $index) {
                $remainingTransactions = $count - $index;
                $type = $this->resolveTransactionType($runningBalance, $remainingTransactions);

                if (TransactionType::Income === $type) {
                    $amount = $this->incomeAmount($runningBalance, $remainingTransactions);
                    $this->createIncomeTransaction($context, $amount);
                    $runningBalance = Decimal::add($runningBalance, $amount);

                    continue;
                }

                $amount = $this->outcomeAmount($runningBalance, $remainingTransactions);
                $this->createOutcomeTransaction($context, $amount);
                $runningBalance = Decimal::subtract($runningBalance, $amount);
            }

            app(\App\Services\Accounts\RecalculateAccountBalance::class)->execute($context->account->refresh())->balance;
        }

        return $this;
    }

    public function withMidexTransactions(int $count): self
    {
        return $this->withMixedTransactions($count);
    }

    public function getAccounts(): Collection
    {
        return $this->generatedAccounts
            ->map(fn (GeneratedAccountContext $context): Account => $context->account->fresh())
            ->values();
    }

    public function getUsers(): Collection
    {
        return $this->generatedAccounts
            ->flatMap(fn (GeneratedAccountContext $context): Collection => $context->allUsers())
            ->unique(fn (User $user): int => $user->id)
            ->values();
    }
    private const MINIMUM_FINAL_BALANCE = '500.00';

    private const INCOME_CONCEPTS = [
        'Salary',
        'Freelance payment',
        'Savings transfer',
        'Bonus',
        'Refund',
        'Sold item',
        'Interest payment',
        'Client deposit',
    ];

    private const OUTCOME_CONCEPTS = [
        'Groceries',
        'Restaurant',
        'Fuel',
        'Streaming subscription',
        'Internet bill',
        'Electricity bill',
        'Pharmacy',
        'Transport',
        'Coffee',
        'Home supplies',
    ];

    private Collection $generatedAccounts;

    private function __construct(
        private readonly User $subject,
    ) {
        $this->generatedAccounts = collect();
    }

    private function rebalanceSelectedAccountMemberships(): void
    {
        foreach ($this->generatedAccounts as $context) {
            $members = $context->allUsers()->values();
            $memberCount = $members->count();

            if (0 === $memberCount) {
                continue;
            }

            $basePercentage = Decimal::divide(100, $memberCount);
            $remaining = '100.00';
            $syncData = [];

            foreach ($members as $position => $member) {
                $percentage = $position === $memberCount - 1
                    ? $remaining
                    : $basePercentage;

                $remaining = Decimal::subtract($remaining, $percentage);
                $syncData[$member->id] = ['percentage' => $percentage];
            }

            $context->account->users()->syncWithoutDetaching($syncData);
        }
    }

    private function createAcceptedInvite(Account $account, User $owner, User $invitee): void
    {
        AccountInvite::factory()->accepted()->createQuietly([
            'account_id' => $account->id,
            'user_id'    => $owner->id,
            'email'      => $invitee->email,
            'percentage' => 0,
        ]);

        $account->users()->syncWithoutDetaching([
            $invitee->id => ['percentage' => 0],
        ]);
    }

    private function resolveTransactionType(string $runningBalance, int $remainingTransactions): TransactionType
    {
        if (0 === $remainingTransactions && Decimal::compare($runningBalance, self::MINIMUM_FINAL_BALANCE) <= 0) {
            return TransactionType::Income;
        }

        if (Decimal::compare($runningBalance, self::MINIMUM_FINAL_BALANCE) < 0) {
            return fake()->boolean(75) ? TransactionType::Income : TransactionType::Outcome;
        }

        return fake()->boolean(60) ? TransactionType::Income : TransactionType::Outcome;
    }

    private function incomeAmount(string $runningBalance, int $remainingTransactions): string
    {
        if (0 === $remainingTransactions && Decimal::compare($runningBalance, self::MINIMUM_FINAL_BALANCE) <= 0) {
            return Decimal::add(Decimal::abs($runningBalance), Decimal::fromMinor(fake()->numberBetween(60000, 180000)));
        }

        return Decimal::fromMinor(fake()->numberBetween(30000, 250000));
    }

    private function outcomeAmount(string $runningBalance, int $remainingTransactions): string
    {
        if (0 === $remainingTransactions) {
            return Decimal::min(Decimal::max(Decimal::subtract($runningBalance, self::MINIMUM_FINAL_BALANCE), 50), 700);
        }

        $maxAmount = Decimal::compare($runningBalance, 1500) > 0
            ? Decimal::min(Decimal::subtract($runningBalance, 200), 1200)
            : 450;

        return Decimal::fromMinor(fake()->numberBetween(5000, Decimal::minor(Decimal::max($maxAmount, 120))));
    }

    private function createIncomeTransaction(GeneratedAccountContext $context, string $amount): void
    {
        $creator = $context->allUsers()->random();

        Transaction::factory()->income()->completed()->createQuietly([
            'account_id'            => $context->account->id,
            'user_id'               => $creator->id,
            'concept'               => fake()->randomElement(self::INCOME_CONCEPTS),
            'amount'                => $amount,
            'percentage'            => 100,
            'scheduled_at'          => Carbon::now()->subDays(fake()->numberBetween(0, 60)),
            'parent_transaction_id' => null,
        ]);
    }

    private function createOutcomeTransaction(GeneratedAccountContext $context, string $amount): void
    {
        $members = $context->allUsers()->values();
        $creator = $members->random();
        $shouldSplit = $members->count() > 1 && fake()->boolean(70);

        $transaction = Transaction::factory()->outcome()->completed()->createQuietly([
            'account_id'            => $context->account->id,
            'user_id'               => $creator->id,
            'concept'               => fake()->randomElement(self::OUTCOME_CONCEPTS),
            'amount'                => $amount,
            'percentage'            => 100,
            'scheduled_at'          => Carbon::now()->subDays(fake()->numberBetween(0, 60)),
            'parent_transaction_id' => null,
        ]);

        if (! $shouldSplit) {
            return;
        }

        $memberCount = $members->count();
        $basePercentage = Decimal::divide(100, $memberCount);
        $remainingPercentage = '100.00';

        foreach ($members as $position => $member) {
            $percentage = $position === $memberCount - 1
                ? $remainingPercentage
                : $basePercentage;

            $remainingPercentage = Decimal::subtract($remainingPercentage, $percentage);

            Transaction::factory()->income()->pending()->createQuietly([
                'account_id'            => $context->account->id,
                'user_id'               => $member->id,
                'concept'               => sprintf('%s - Shared portion of %s', $transaction->concept, $member->name),
                'amount'                => Decimal::ratio($amount, $percentage, 100),
                'percentage'            => $percentage,
                'scheduled_at'          => $transaction->scheduled_at,
                'parent_transaction_id' => $transaction->id,
            ]);
        }
    }
}
