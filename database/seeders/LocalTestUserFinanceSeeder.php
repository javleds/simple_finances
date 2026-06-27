<?php

namespace Database\Seeders;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class LocalTestUserFinanceSeeder extends Seeder
{
    private const USER_EMAIL = 'test@example.com';

    public function run(): void
    {
        $user = $this->seedUser();
        $accounts = $this->seedAccounts($user);

        $this->seedTransactions($user, $accounts);
        $this->updateBalances($accounts);
    }

    private function seedUser(): User
    {
        $user = User::withoutGlobalScopes()->firstOrNew([
            'email' => self::USER_EMAIL,
        ]);

        if (! $user->exists) {
            $user->name = 'Test User';
            $user->password = Hash::make('password');
        }

        $user->email_verified_at ??= now();
        $user->save();

        return $user;
    }

    private function seedAccounts(User $user): array
    {
        $accounts = [];

        foreach ($this->accountDefinitions() as $definition) {
            $account = Account::withoutGlobalScopes()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $definition['name'],
                ],
                [
                    'description' => 'Cuenta individual de prueba local',
                    'color' => $definition['color'],
                    'balance' => 0,
                    'credit_card' => false,
                    'credit_line' => null,
                    'cutoff_day' => null,
                    'next_cutoff_date' => null,
                    'available_credit' => null,
                    'spent' => null,
                    'virtual' => false,
                ],
            );

            $account->users()->syncWithoutDetaching([
                $user->id => ['percentage' => 100],
            ]);

            $accounts[$definition['name']] = $account;
        }

        return $accounts;
    }

    private function seedTransactions(User $user, array $accounts): void
    {
        for ($monthOffset = 11; $monthOffset >= 0; $monthOffset--) {
            $month = now()->startOfMonth()->subMonths($monthOffset);

            $this->seedMonthlyIncome($user, $accounts, $month, $monthOffset);
            $this->seedMonthlyExpenses($user, $accounts, $month, $monthOffset);
        }
    }

    private function seedMonthlyIncome(User $user, array $accounts, Carbon $month, int $monthOffset): void
    {
        $this->seedTransaction($user, $accounts['Nomina BBVA'], [
            'concept' => 'Sueldo mensual',
            'amount' => 45000,
            'type' => TransactionType::Income,
            'scheduled_at' => $month->copy()->day(5),
        ]);

        $this->seedTransaction($user, $accounts['Ahorro mensual'], [
            'concept' => 'Ahorro programado',
            'amount' => 6000,
            'type' => TransactionType::Income,
            'scheduled_at' => $month->copy()->day(7),
        ]);

        $this->seedTransaction($user, $accounts['Efectivo'], [
            'concept' => 'Retiro para efectivo',
            'amount' => 3000,
            'type' => TransactionType::Income,
            'scheduled_at' => $month->copy()->day(9),
        ]);

        if ($monthOffset % 3 !== 0) {
            return;
        }

        $this->seedTransaction($user, $accounts['Nomina BBVA'], [
            'concept' => 'Bono trimestral',
            'amount' => 8000,
            'type' => TransactionType::Income,
            'scheduled_at' => $month->copy()->day(15),
        ]);
    }

    private function seedMonthlyExpenses(User $user, array $accounts, Carbon $month, int $monthOffset): void
    {
        foreach ($this->monthlyExpenseDefinitions() as $definition) {
            $variation = 1 + ((($monthOffset % 5) - 2) * 0.035);
            $amount = round($definition['amount'] * $variation, 2);

            $this->seedTransaction($user, $accounts[$definition['account']], [
                'concept' => $definition['concept'],
                'amount' => $amount,
                'type' => TransactionType::Outcome,
                'scheduled_at' => $month->copy()->day(min($definition['day'], $month->daysInMonth)),
            ]);
        }

        if ($monthOffset % 4 !== 0) {
            return;
        }

        $this->seedTransaction($user, $accounts['Gastos diarios'], [
            'concept' => 'Mantenimiento auto',
            'amount' => 4200,
            'type' => TransactionType::Outcome,
            'scheduled_at' => $month->copy()->day(20),
        ]);
    }

    private function seedTransaction(User $user, Account $account, array $data): void
    {
        $scheduledAt = $data['scheduled_at'];

        Transaction::updateOrCreate(
            [
                'user_id' => $user->id,
                'account_id' => $account->id,
                'concept' => $data['concept'],
                'scheduled_at' => $scheduledAt->toDateString(),
            ],
            [
                'amount' => $data['amount'],
                'type' => $data['type'],
                'status' => TransactionStatus::Completed,
                'percentage' => 100,
                'financial_goal_id' => null,
                'parent_transaction_id' => null,
            ],
        );
    }

    private function updateBalances(array $accounts): void
    {
        foreach ($accounts as $account) {
            $account->fresh()->updateBalance();
        }
    }

    private function accountDefinitions(): array
    {
        return [
            ['name' => 'Nomina BBVA', 'color' => '#2563eb'],
            ['name' => 'Debito Nu', 'color' => '#7c3aed'],
            ['name' => 'Efectivo', 'color' => '#16a34a'],
            ['name' => 'Ahorro mensual', 'color' => '#0891b2'],
            ['name' => 'Gastos diarios', 'color' => '#f97316'],
        ];
    }

    private function monthlyExpenseDefinitions(): array
    {
        return [
            ['account' => 'Nomina BBVA', 'concept' => 'Renta departamento', 'amount' => 12500, 'day' => 2],
            ['account' => 'Gastos diarios', 'concept' => 'Supermercado', 'amount' => 3200, 'day' => 6],
            ['account' => 'Debito Nu', 'concept' => 'Internet y celular', 'amount' => 980, 'day' => 8],
            ['account' => 'Debito Nu', 'concept' => 'Streaming y software', 'amount' => 650, 'day' => 10],
            ['account' => 'Gastos diarios', 'concept' => 'Transporte', 'amount' => 1600, 'day' => 14],
            ['account' => 'Efectivo', 'concept' => 'Comidas fuera', 'amount' => 2100, 'day' => 18],
            ['account' => 'Debito Nu', 'concept' => 'Farmacia y salud', 'amount' => 900, 'day' => 22],
            ['account' => 'Gastos diarios', 'concept' => 'Compras personales', 'amount' => 1800, 'day' => 26],
        ];
    }
}
