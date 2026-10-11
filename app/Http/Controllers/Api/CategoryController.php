<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Account;
use App\Models\Category;
use App\Services\Api\AuthorizeAccountAccess;
use App\Services\Categories\DeleteCategory;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryController extends ApiController
{
    public function __construct(private readonly AuthorizeAccountAccess $authorizeAccountAccess)
    {
    }

    public function index(Request $request, ?Account $account = null): JsonResponse
    {
        $this->ensureAccess($account);
        $canManage = $this->canManage($account);
        $query = $this->catalog($account)->withCount('transactions')->orderBy('name');
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($filters['search'] ?? '');
        if ('' !== $search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        return $this->respond([
            'data' => $query->get()->each(static fn (Category $category) => $category->setAttribute('can_manage', $canManage)),
            'meta' => [
                'scope'        => $account?->uses_shared_categories ? 'shared' : 'personal',
                'account_id'   => $account?->uses_shared_categories ? $account->id : null,
                'account_name' => $account?->name,
                'can_manage'   => $canManage,
            ],
        ]);
    }

    public function store(Request $request, ?Account $account = null): JsonResponse
    {
        $this->ensureAccess($account);
        $name = $this->name($request);
        $normalized = mb_strtolower($name);
        if ($this->catalog($account)->where('normalized_name', $normalized)->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe una categoría con ese nombre.']);
        }

        try {
            $category = Category::query()->create([
                'name'               => $name,
                'normalized_name'    => $normalized,
                'user_id'            => $account?->uses_shared_categories ? null : auth()->id(),
                'account_id'         => $account?->uses_shared_categories ? $account->id : null,
                'created_by_user_id' => auth()->id(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Ya existe una categoría con ese nombre.']);
        }
        $category->setAttribute('can_manage', $this->canManage($account));
        $category->setAttribute('transactions_count', 0);

        return $this->respondModel($category, status: 201);
    }

    public function update(Request $request, Category $category, ?Account $account = null): JsonResponse
    {
        $this->ensureCategory($category, $account);
        abort_unless($this->canManage($account), 403);
        $name = $this->name($request);
        $normalized = mb_strtolower($name);
        if ($this->catalog($account)->where('normalized_name', $normalized)->whereKeyNot($category->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe una categoría con ese nombre.']);
        }

        try {
            $category->update(['name' => $name, 'normalized_name' => $normalized]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Ya existe una categoría con ese nombre.']);
        }
        $category->loadCount('transactions')->setAttribute('can_manage', true);

        return $this->respondModel($category);
    }

    public function delete(Request $request, Category $category, DeleteCategory $deleteCategory, ?Account $account = null): JsonResponse
    {
        $this->ensureCategory($category, $account);
        abort_unless($this->canManage($account), 403);
        $data = $request->validate([
            'action'             => ['nullable', 'in:uncategorize,reassign'],
            'target_category_id' => ['required_if:action,reassign', 'nullable', 'integer'],
        ]);
        $deleteCategory->execute($category, $data['action'] ?? null, isset($data['target_category_id']) ? (int) $data['target_category_id'] : null);

        return $this->respondDeleted('Categoría eliminada.');
    }

    public function updateShared(Request $request, Account $account, Category $category): JsonResponse
    {
        return $this->update($request, $category, $account);
    }

    public function deleteShared(Request $request, Account $account, Category $category, DeleteCategory $deleteCategory): JsonResponse
    {
        return $this->delete($request, $category, $deleteCategory, $account);
    }

    private function catalog(?Account $account): Builder
    {
        if ($account?->uses_shared_categories) {
            return Category::query()->where('account_id', $account->id);
        }

        return Category::query()->whereNull('account_id')->where('user_id', auth()->id());
    }

    private function ensureAccess(?Account $account): void
    {
        if (null !== $account) {
            $this->authorizeAccountAccess->ensureMember($account);
        }
    }

    private function ensureCategory(Category $category, ?Account $account): void
    {
        $this->ensureAccess($account);
        abort_unless($this->catalog($account)->whereKey($category->id)->exists(), 404);
    }

    private function canManage(?Account $account): bool
    {
        return ! $account?->uses_shared_categories || $account->user_id === auth()->id();
    }

    private function name(Request $request): string
    {
        $request->validate(['name' => ['required', 'string']]);
        $name = preg_replace('/\s+/u', ' ', trim($request->string('name')->toString()));
        $request->merge(['name' => $name]);
        $request->validate(['name' => ['required', 'string', 'max:100']]);

        return $name;
    }
}
