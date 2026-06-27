<?php

namespace App\Dto;

use Illuminate\Http\Request;

class ApiIndexCriteriaDto
{
    /**
     * @param  array<int, string>  $filterColumns
     * @param  array<string, string>  $nullableBooleanFilters
     * @param  array<int, string>  $searchColumns
     */
    public function __construct(
        public readonly Request $request,
        public readonly array $filterColumns = [],
        public readonly array $nullableBooleanFilters = [],
        public readonly array $searchColumns = [],
    ) {}
}
