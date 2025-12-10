<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class AccountBalancePlot extends ChartWidget
{
    protected static ?string $heading = 'Balance por cuenta';

    protected int|string|array $columnSpan = [
        'sm' => 1,
        'lg' => 1,
    ];

    protected static ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $accounts = Account::query()
            ->orderBy('name')
            ->get(['name', 'balance']);

        $palette = $this->getPalette();
        $datasets = [];
        $paletteSize = count($palette);

        foreach ($accounts as $index => $account) {
            $datasets[] = [
                'label' => $account->name,
                'data' => collect($accounts->keys())
                    ->map(fn (int $position) => $position === $index ? $this->transformBalance((float) $account->balance) : 0)
                    ->all(),
                'backgroundColor' => $palette[$index % $paletteSize]['background'],
                'borderColor' => $palette[$index % $paletteSize]['border'],
                'borderWidth' => 2,
            ];
        }

        return [
            'labels' => $accounts->pluck('name')->all(),
            'datasets' => $datasets,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            (function () {
                var isMobile = matchMedia('(max-width: 640px)').matches;
                var restoreBalance = function (value) {
                    var sign = value === 0 ? 0 : (value > 0 ? 1 : -1);
                    var restored = Math.exp(Math.abs(value)) - 1;

                    return sign * restored;
                };

                return {
                    scales: {
                        x: {
                            ticks: {
                                display: !isMobile,
                            },
                            grid: {
                                display: !isMobile,
                            },
                        },
                        y: {
                            type: 'linear',
                            ticks: {
                                display: !isMobile,
                                callback: function (value) {
                                    var restored = restoreBalance(value);
                                    return '$' + restored.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                },
                            },
                        },
                    },
                    datasets: {
                        bar: {
                            categoryPercentage: isMobile ? 1 : 0.9,
                            barPercentage: isMobile ? 1 : 0.9,
                        },
                    },
                    plugins: {
                        annotation: {
                            annotations: {
                                line1: {
                                    type: 'line',
                                    yMin: 0,
                                    yMax: 0,
                                    borderColor: 'rgba(255, 99, 132, 0.8)',
                                    borderWidth: 2,
                                    borderDash: [6, 6],
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    var restored = restoreBalance(context.parsed.y);
                                    return context.dataset.label + ': $' + restored.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                },
                            },
                        },
                    }
                };
            })()
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

    private function transformBalance(float $balance): float
    {
        if ($balance === 0.0) {
            return 0.0;
        }

        $magnitude = log1p(abs($balance));

        if ($balance > 0.0) {
            return $magnitude;
        }

        return -$magnitude;
    }
}
