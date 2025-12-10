<?php

namespace App\Filament\Filters;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;

class DateRangeFilter
{
    public static function make(string $name, string $label): Filter
    {

        return Filter::make($name)
            ->form([
                DatePicker::make('from')
                    ->label(fn () => sprintf('[%s] Desde', $label))
                    ->native(false)
                    ->closeOnDateSelection(),
                DatePicker::make('until')
                    ->label(fn () => sprintf('[%s] Hasta', $label))
                    ->native(false)
                    ->closeOnDateSelection(),
            ])
            ->query(function (Builder $query, array $data) use ($name): Builder {
                return $query
                    ->when(
                        $data['from'],
                        fn (Builder $query, $date): Builder => $query->whereDate($name, '>=', $date),
                    )
                    ->when(
                        $data['until'],
                        fn (Builder $query, $date): Builder => $query->whereDate($name, '<=', $date),
                    );
            })
            ->indicateUsing(function (array $data) use ($label): array {
                $indicators = [];

                if ($data['from'] ?? null) {
                    $indicators[] = Indicator::make($label.' Desde '.Carbon::parse($data['from'])->toFormattedDateString())
                        ->removeField('from');
                }

                if ($data['until'] ?? null) {
                    $indicators[] = Indicator::make($label.' Hasta '.Carbon::parse($data['until'])->toFormattedDateString())
                        ->removeField('until');
                }

                return $indicators;
            });
    }
}
