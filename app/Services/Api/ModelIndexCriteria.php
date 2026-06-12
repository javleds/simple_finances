<?php

namespace App\Services\Api;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ModelIndexCriteria
{
    public function apply(Builder $query, Request $request, array $nullableBooleanFilters = []): Builder
    {
        $model = $query->getModel();
        $columns = Schema::getColumnListing($model->getTable());

        foreach ($request->query() as $key => $value) {
            if ($this->applyNullableBooleanFilter($query, $key, $value, $nullableBooleanFilters)) {
                continue;
            }

            if ($this->shouldSkip($key, $value, $columns)) {
                continue;
            }

            $values = $this->normalizeFilterValues($model, $key, $value);

            if (count($values) > 1) {
                $query->whereIn($key, $values);

                continue;
            }

            $query->where($key, $values[0]);
        }

        return $query;
    }

    private function applyNullableBooleanFilter(
        Builder $query,
        string $key,
        mixed $value,
        array $nullableBooleanFilters
    ): bool {
        $column = $nullableBooleanFilters[$key] ?? null;

        if ($column === null) {
            return false;
        }

        $parsedValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($parsedValue === null) {
            return true;
        }

        if ($parsedValue) {
            $query->whereNotNull($column);

            return true;
        }

        $query->whereNull($column);

        return true;
    }

    private function shouldSkip(string $key, mixed $value, array $columns): bool
    {
        if (in_array($key, ['page', 'per_page'], true)) {
            return true;
        }

        if (! in_array($key, $columns, true)) {
            return true;
        }

        if (is_array($value)) {
            return true;
        }

        return $value === null || $value === '';
    }

    private function normalizeValue(Model $model, string $key, mixed $value): mixed
    {
        $casts = $model->getCasts();
        $cast = $casts[$key] ?? null;

        if ($cast === 'bool' || $cast === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        if ($cast === 'int' || $cast === 'integer') {
            return (int) $value;
        }

        if ($cast === 'float' || $cast === 'double' || $cast === 'real') {
            return (float) $value;
        }

        if (is_string($cast) && enum_exists($cast)) {
            return $this->normalizeEnumValue($cast, $value);
        }

        return $value;
    }

    private function normalizeFilterValues(Model $model, string $key, mixed $value): array
    {
        if (! is_string($value) || ! str_contains($value, ',')) {
            return [$this->normalizeValue($model, $key, $value)];
        }

        $values = array_filter(
            array_map(trim(...), explode(',', $value)),
            fn (string $item): bool => $item !== '',
        );

        if ($values === []) {
            return [$this->normalizeValue($model, $key, $value)];
        }

        return array_values(array_map(
            fn (string $item): mixed => $this->normalizeValue($model, $key, $item),
            $values,
        ));
    }

    private function normalizeEnumValue(string $enumClass, mixed $value): mixed
    {
        if (! is_subclass_of($enumClass, BackedEnum::class)) {
            return $value;
        }

        $enum = $enumClass::tryFrom($value);

        return $enum?->value ?? $value;
    }
}
