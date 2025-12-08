<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class AccountBalancePlot extends ChartWidget
{
    protected static ?string $heading = 'Balance por cuenta';

    protected int | string | array $columnSpan = [
        'sm' => 1,
        'lg' => 1,
    ];

    protected static ?string $maxHeight = '150px';

    protected function getData(): array
    {
        $accounts = Account::query()
            ->orderBy('name')
            ->get(['name', 'balance']);

        $labels = $accounts->pluck('name')->all();
        $balances = $accounts->pluck('balance')->all();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Balance',
                    'data' => $balances,
                    'backgroundColor' => '#51d6a3',
                    'borderColor' => '#07ab6c',
                    'barThickness' => 20,
                ],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<JS
            {
                scales: {
                    y: {
                        ticks: {
                            callback: (value) => '$' + value.toLocaleString(),
                        },
                    },
                },
            }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
