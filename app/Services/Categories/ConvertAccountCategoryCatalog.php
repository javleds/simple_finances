<?php

declare(strict_types=1);

namespace App\Services\Categories;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class ConvertAccountCategoryCatalog
{
    public function execute(Account $account): void
    {
        DB::transaction(static function () use ($account): void {
            $locked = Account::withoutGlobalScopes()->lockForUpdate()->findOrFail($account->id);

            if ($locked->uses_shared_categories) {
                return;
            }

            $categories = Category::query()->whereIn('id', Transaction::withoutGlobalScopes()->where('account_id', $account->id)->whereNotNull('category_id')->select('category_id'))->lockForUpdate()->get();

            foreach ($categories as $category) {
                $shared = Category::query()->firstOrCreate([
                    'account_id'      => $account->id,
                    'normalized_name' => $category->normalized_name,
                ], [
                    'user_id'            => null,
                    'name'               => $category->name,
                    'created_by_user_id' => $category->created_by_user_id,
                ]);
                Transaction::withoutGlobalScopes()->where('account_id', $account->id)->where('category_id', $category->id)->update(['category_id' => $shared->id]);
            }

            $locked->update(['uses_shared_categories' => true]);
            $account->uses_shared_categories = true;
        });
    }
}
