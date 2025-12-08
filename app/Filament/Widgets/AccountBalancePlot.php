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

        $palette = $this->getPalette();
        $datasets = [];

        foreach ($accounts as $index => $account) {
            $datasets[] = [
                'label' => $account->name,
                'data' => collect($accounts->keys())
                    ->map(fn (int $position) => $position === $index ? $account->balance : 0)
                    ->all(),
                'backgroundColor' => $palette[$index % count($palette)]['background'],
                'borderColor' => $palette[$index % count($palette)]['border'],
                'borderWidth' => 2,
                'barThickness' => 22,
            ];
        }

        return [
            'labels' => $accounts->pluck('name')->all(),
            'datasets' => $datasets,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<JS
            {
                plugins: {
                    legend: {
                        labels: {
                            color: '#e5e7eb',
                        },
                    },
                },
                scales: {
                    y: {
                        ticks: {
                            color: '#e5e7eb',
                            callback: (value) => '$' + value.toLocaleString(),
                        },
                        grid: {
                            color: '#374151',
                        },
                    },
                    x: {
                        ticks: {
                            color: '#e5e7eb',
                            autoSkip: false,
                        },
                        grid: {
                            color: '#1f2937',
                        },
                        stacked: true,
                    },
                },
            }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    private function getPalette(): array
    {
        return [
            ['background' => '#38bdf825', 'border' => '#38bdf8'],
            ['background' => '#f59e0b25', 'border' => '#f59e0b'],
            ['background' => '#10b98125', 'border' => '#10b981'],
            ['background' => '#ef444425', 'border' => '#ef4444'],
            ['background' => '#6366f125', 'border' => '#6366f1'],
            ['background' => '#8b5cf625', 'border' => '#8b5cf6'],
            ['background' => '#ec489925', 'border' => '#ec4899'],
            ['background' => '#f9731625', 'border' => '#f97316'],
            ['background' => '#22c55e25', 'border' => '#22c55e'],
            ['background' => '#0ea5e925', 'border' => '#0ea5e9'],
        ];
    }
}
