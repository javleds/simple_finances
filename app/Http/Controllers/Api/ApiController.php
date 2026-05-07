<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\ModelIndexCriteria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

abstract class ApiController extends Controller
{
    protected function respond(array $payload = [], int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    protected function respondModel(Model $model, array $relations = [], int $status = 200): JsonResponse
    {
        if ($relations !== []) {
            $model->loadMissing($relations);
        }

        return $this->respond([
            'data' => $model,
        ], $status);
    }

    protected function respondCollection(Collection $collection): JsonResponse
    {
        return $this->respond([
            'data' => $collection->values(),
        ]);
    }

    protected function respondPaginated(Builder $query, Request $request): JsonResponse
    {
        $perPage = max(1, (int) $request->integer('per_page', 20));
        $paginator = app(ModelIndexCriteria::class)
            ->apply($query, $request)
            ->paginate($perPage)
            ->withQueryString();

        return $this->respond([
            'data' => $paginator->items(),
            'meta' => $this->paginationMeta($paginator),
        ]);
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }
}
