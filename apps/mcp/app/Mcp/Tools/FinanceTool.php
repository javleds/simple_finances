<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use function abort_unless;
use function app;
use function array_diff;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function array_map;
use function array_merge;
use function array_sum;
use function array_values;
use function count;

use FinSi\FinanceDomain\ReadModels\FinancialReadTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

use function implode;
use function in_array;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

abstract class FinanceTool extends Tool
{
    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false];
    }

    public function schema(JsonSchema $schema): array
    {
        $types = [
            'name'            => $schema->string()->max(100),
            'account_id'      => $schema->integer()->min(1),
            'include_virtual' => $schema->boolean(),
            'cursor'          => $schema->string()->pattern('^[0-9]{1,7}$'),
            'limit'           => $schema->integer()->min(1)->max(100),
            'start_date'      => $schema->string()->required()->description('Inclusive YYYY-MM-DD'),
            'end_date'        => $schema->string()->required()->description('Inclusive YYYY-MM-DD'),
            'account_ids'     => $schema->array()->items($schema->integer()->min(1))->max(100),
            'category_ids'    => $schema->array()->items($schema->integer()->min(1))->max(100),
            'scope'           => $schema->string()->enum(['account_total'])->description('Total account expenses, not personal participation or cash paid.'),
            'group_by'        => $schema->string()->enum(['account', 'category', 'month', 'concept']),
            'groups'          => $schema->array()->items($schema->object([
                'label'           => $schema->string()->max(120)->required(),
                'transaction_ids' => $schema->array()->items($schema->integer()->min(1))->min(1)->max(1000)->required(),
            ]))->min(1)->max(50)->required(),
        ];
        $result = array_intersect_key($types, array_flip($this->fields));
        if (in_array($this->name, ['get_account_balance', 'get_payable_breakdown'], true)) {
            $result['account_id']->required();
        }

        return $result;
    }

    public function handle(Request $request, FinancialReadTools $tools): Response | ResponseFactory
    {
        $rules = [
            'name'            => 'sometimes|string|max:100', 'account_id' => 'sometimes|integer|min:1',
            'include_virtual' => 'sometimes|boolean', 'cursor' => ['sometimes', 'regex:/^[0-9]{1,7}$/'],
            'limit'           => 'sometimes|integer|min:1|max:100', 'start_date' => 'required|date_format:Y-m-d',
            'end_date'        => 'required|date_format:Y-m-d', 'account_ids' => 'sometimes|array|max:100',
            'category_ids'    => 'sometimes|array|max:100', 'scope' => 'sometimes|in:account_total',
            'group_by'        => 'sometimes|in:account,category,month,concept', 'groups' => 'required|array|min:1|max:50',
        ];
        $rules = array_intersect_key($rules, array_flip($this->fields));
        foreach (['account_ids', 'category_ids'] as $field) {
            if (in_array($field, $this->fields, true)) {
                $rules[$field . '.*'] = 'integer|min:1|distinct';
            }
        }
        if (isset($rules['groups'])) {
            $rules += ['groups.*' => 'array:label,transaction_ids', 'groups.*.label' => 'required|string|max:120', 'groups.*.transaction_ids' => 'required|array|min:1|max:1000', 'groups.*.transaction_ids.*' => 'integer|min:1'];
        }
        if (in_array($this->name, ['get_account_balance', 'get_payable_breakdown'], true)) {
            $rules['account_id'] = 'required|integer|min:1';
        }

        try {
            if ([] !== array_diff(array_keys($request->all()), $this->fields)) {
                return $this->error('validation_error', 'Unknown arguments; identity cannot be supplied as a tool argument.');
            }
            $arguments = $request->validate($rules);
            if (isset($arguments['groups']) && array_sum(array_map(fn (array $group): int => count($group['transaction_ids']), $arguments['groups'])) > 1000) {
                return $this->error('validation_error', 'At most 1000 transactions may be grouped.');
            }
            $actorId = (int) app('request')->user()?->getAuthIdentifier();
            abort_unless($actorId > 0, 401);

            return Response::structured($tools->execute($actorId, $this->name, $arguments));
        } catch (ValidationException $exception) {
            return $this->error('validation_error', 'Invalid arguments: ' . implode(' ', array_merge(...array_values($exception->errors()))));
        } catch (\Illuminate\Database\QueryException $exception) {
            return $this->error('dependency_unavailable', 'Financial data is temporarily unavailable.');
        } catch (ModelNotFoundException | HttpExceptionInterface $exception) {
            return $this->error('not_found', 'Resource not available for this actor.');
        }
    }

    protected array $fields = [];

    private function error(string $code, string $message): ResponseFactory
    {
        return Response::make(Response::error($message))->withStructuredContent(['error' => ['code' => $code, 'message' => $message]]);
    }
}
