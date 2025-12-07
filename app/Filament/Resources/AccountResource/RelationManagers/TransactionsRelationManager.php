<?php

namespace App\Filament\Resources\AccountResource\RelationManagers;

use App\Dto\TransactionFormDto;
use App\Dto\UserPaymentDto;
use App\Enums\Action;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\BulkTransactionSaved;
use App\Events\TransactionSaved;
use App\Filament\Actions\DeferredTransactionAction;
use App\Filament\Exports\TransactionExporter;
use App\Filament\Filters\DateRangeFilter;
use App\Models\Account;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionCreator;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use MailerSend\Helpers\Arr;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transacciones';
    protected static ?string $label = 'Transacción';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function getTabs(): array
    {
        /** @var Account $account */
        $account = $this->getOwnerRecord();

        if (!$account->isCreditCard()) {
            return [];
        }

        return [
            Tab::make('Hasta hoy')->modifyQueryUsing(fn (Builder $query) => $query->beforeOrEqualsTo($this->getOwnerRecord()->next_cutoff_date)),
            Tab::make('Todas'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\ToggleButtons::make('type')
                    ->label('Tipo')
                    ->inline()
                    ->grouped()
                    ->options(TransactionType::class)
                    ->default(TransactionType::Outcome)
                    ->required()
                    ->live()
                    ->columnSpan(fn (Forms\Get $get) => $get('type') === TransactionType::Outcome ? 'full' : null),
                Forms\Components\ToggleButtons::make('status')
                    ->label('Estatus')
                    ->inline()
                    ->grouped()
                    ->options(TransactionStatus::class)
                    ->default(TransactionStatus::Completed)
                    ->required()
                    ->hidden(fn (Forms\Get $get) => $get('type') !== TransactionType::Income),
                Forms\Components\TextInput::make('concept')
                    ->label('Concepto')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')
                    ->label('Cantidad')
                    ->prefix('$')
                    ->required()
                    ->numeric(),
                Forms\Components\Checkbox::make('split_between_users')
                    ->label('Dividir entre usuarios de la cuenta')
                    ->default(false)
                    ->hidden(function (Forms\Get $get) {
                        if ($get('type') !== TransactionType::Outcome) {
                            return true;
                        }

                        $account = $this->getOwnerRecord();

                        if (!$account) {
                            return true;
                        }

                        return $account->users()->count() <= 1;
                    })
                    ->live()
                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                        $account = $this->getOwnerRecord();

                        if (!$account) {
                            return $set('user_payments', []);
                        }

                        $set('user_payments', $account->users()->withoutGlobalScopes()->get()->map(function (User $user) {
                            return [
                                'user_id' => $user->id,
                                'name' => $user->name,
                                'percentage' => $user->pivot->percentage ?? 0.0,
                            ];
                        })->toArray());
                    }),
                Forms\Components\Repeater::make('user_payments')
                    ->hidden(fn (Forms\Get $get) => !$get('split_between_users'))
                    ->label('Usuarios')
                    ->rules([
                        function (Forms\Get $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                if (!$get('split_between_users')) {
                                    return;
                                }

                                $totalPercentage = (float) collect($value)->sum('percentage');

                                if ($totalPercentage !== 100.0) {
                                    $fail('La suma de los porcentajes debe ser igual a 100.00 %.');
                                }
                            };
                        },
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('user_id')
                            ->label('ID')
                            ->numeric()
                            ->required()
                            ->readOnly(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->readOnly(),
                        Forms\Components\TextInput::make('percentage')
                            ->label('Porcentaje')
                            ->suffix('%')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->deletable(false)
                    ->reorderable(false)
                    ->maxItems(function () {
                        $account = $this->getOwnerRecord();

                        if (!$account) {
                            return 0;
                        }

                        return $account->users()->count();
                    })
                    ->minItems(function () {
                        $account = $this->getOwnerRecord();

                        if (!$account) {
                            return 0;
                        }

                        return $account->users()->count();
                    }),
                Forms\Components\DatePicker::make('scheduled_at')
                    ->label('Fecha')
                    ->prefixIcon('heroicon-o-calendar')
                    ->default(Carbon::now())
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required(),
                Forms\Components\Select::make('financial_goal_id')
                    ->options(fn () => FinancialGoal::where('user_id', auth()->id())->where('account_id', $this->getOwnerRecord()->id)->pluck('name', 'id'))
                    ->label('Meta financiera')
                    ->disabled(fn (Forms\Get $get) => $get('type') === TransactionType::Outcome),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderBy('scheduled_at', 'desc')->orderBy('created_at', 'desc'))
            ->recordTitleAttribute('concept')
            ->columns([
                Tables\Columns\TextColumn::make('concept')
                    ->label('Concepto')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Cantidad')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => as_money($state))
                    ->searchable()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make('income')
                            ->query(fn (\Illuminate\Database\Query\Builder $query) => $query->where('type', TransactionType::Income)->where('status', TransactionStatus::Completed))
                            ->formatStateUsing(fn ($state) => as_money($state))
                            ->label('Ingresos'),
                        Tables\Columns\Summarizers\Sum::make('outcome')
                            ->query(fn (\Illuminate\Database\Query\Builder $query) => $query->where('type', TransactionType::Outcome)->where('status', TransactionStatus::Completed))
                            ->formatStateUsing(fn ($state) => as_money($state))
                            ->label('Egresos'),
                    ]),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (TransactionStatus $state) => $state->getLabel())
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Fecha')
                    ->dateTime('F d, Y')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Creado por')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('financialGoal.name')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Meta financiera')
                    ->sortable()
                    ->searchable(),
            ])
            ->groups([
                Tables\Grouping\Group::make('scheduled_at')->label('Fecha'),
                Tables\Grouping\Group::make('user.name')
                    ->label('Usuario'),
                Tables\Grouping\Group::make('financialGoal.name')
                    ->label('Meta financiera'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(TransactionType::class)
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estatus')
                    ->options(TransactionStatus::class)
                    ->multiple()
                    ->searchable(),
                DateRangeFilter::make('scheduled_at', 'Fecha de pago'),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Creado por')
                    ->options(fn () => $this->getOwnerRecord()->users()->get()->pluck('name', 'id'))
                    ->preload()
                    ->searchable()
                    ->multiple(),
            ])
            ->headerActions([
                DeferredTransactionAction::makeWithOwnerRecord($this->getOwnerRecord()),
                Tables\Actions\CreateAction::make()
                    ->modalHeading('Crear Transacción')
                    ->action(function (array $data, Component $livewire) {
                        app(TransactionCreator::class)->execute(new TransactionFormDto(
                            id: null,
                            type: $data['type'],
                            status: $data['status'] ?? TransactionStatus::Completed,
                            concept: $data['concept'],
                            amount: (float) $data['amount'],
                            accountId: $this->getOwnerRecord()->id,
                            splitBetweenUsers: $data['split_between_users'] ?? false,
                            userPayments: collect($data['user_payments'] ?? [])->map(fn (array $userPayment) => UserPaymentDto::fromFormArray($userPayment))->all() ?? [],
                            scheduledAt: $data['scheduled_at'],
                            finanialGoalId: $data['financial_goal_id'] ?? null,
                        ));

                        Notification::make('transaction_added')
                            ->success()
                            ->title('Transacción creada')
                            ->send();

                        $livewire->dispatch('refreshAccount');
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->after(function (Transaction $record, Component $livewire) {
                        event(new TransactionSaved($record, Action::Updated));

                        $livewire->dispatch('refreshAccount');
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->after(function (Transaction $record, Component $livewire) {
                        event(new TransactionSaved($record, Action::Deleted));

                        $livewire->dispatch('refreshAccount');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->label('Exportar seleccionados')
                        ->exporter(TransactionExporter::class)
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function (Collection $records, Component $livewire) {
                            event(new BulkTransactionSaved($records));

                            $livewire->dispatch('refreshAccount');
                        }),
                ]),
            ]);
    }
}
