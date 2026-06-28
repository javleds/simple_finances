<?php

namespace App\Services\Api;

use App\Dto\ApiIndexCriteriaDto;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ModelIndexCriteria
{
    public function apply(Builder $query, ApiIndexCriteriaDto $criteria): Builder
    {
        $model = $query->getModel();

        $this->applySearch($query, $criteria);

        foreach ($criteria->request->query() as $key => $value) {
            if ($this->applyNullableBooleanFilter($query, $key, $value, $criteria)) {
                continue;
            }

            if ($this->shouldSkip($key, $value, $criteria)) {
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

    private function applySearch(Builder $query, ApiIndexCriteriaDto $criteria): void
    {
        $search = $criteria->request->string('search')->trim()->toString();

        if ($search === '' || $criteria->searchColumns === []) {
            return;
        }

        $query->where(function (Builder $query) use ($search, $criteria): void {
            foreach ($criteria->searchColumns as $column) {
                $query->orWhere($column, 'like', '%'.$search.'%');
            }
        });
    }

    private function applyNullableBooleanFilter(
        Builder $query,
        string $key,
        mixed $value,
        ApiIndexCriteriaDto $criteria,
    ): bool {
        $column = $criteria->nullableBooleanFilters[$key] ?? null;

        if ($column === null) {
            return false;
        }

        $values = $this->rawFilterValues($value);
        $parsedValue = $values === []
            ? null
            : filter_var($values[0], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

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

    private function shouldSkip(string $key, mixed $value, ApiIndexCriteriaDto $criteria): bool
    {
        if (in_array($key, ['page', 'per_page', 'search'], true)) {
            return true;
        }

        if (! in_array($key, $criteria->filterColumns, true)) {
            return true;
        }

        if (is_array($value)) {
            return $this->rawFilterValues($value) === [];
        }

        return $value === null || trim((string) $value) === '';
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
        $values = $this->rawFilterValues($value);

        return array_values(array_map(
            fn (mixed $item): mixed => $this->normalizeValue($model, $key, $item),
            $values,
        ));
    }

    /**
     * @return array<int, mixed>
     */
    private function rawFilterValues(mixed $value): array
    {
        $rawValues = is_array($value) ? $value : [$value];
        $values = [];

        foreach ($rawValues as $rawValue) {
            $items = is_string($rawValue) && str_contains($rawValue, ',')
                ? explode(',', $rawValue)
                : [$rawValue];

            foreach ($items as $item) {
                $normalizedItem = is_string($item) ? trim($item) : $item;

                if ($normalizedItem === null || $normalizedItem === '') {
                    continue;
                }

                $values[] = $normalizedItem;
            }
        }

        return $values;
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
