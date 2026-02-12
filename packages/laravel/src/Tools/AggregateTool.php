<?php

declare(strict_types=1);

namespace Botovis\Laravel\Tools;

use Botovis\Core\Tools\ToolResult;
use Illuminate\Support\Facades\DB;

/**
 * Run an aggregate function on a table column.
 *
 * Supports COUNT, SUM, AVG, MIN, MAX, and GROUP BY.
 */
class AggregateTool extends BaseTool
{
    public function name(): string
    {
        return 'aggregate';
    }

    public function description(): string
    {
        return 'Run aggregate functions (COUNT, SUM, AVG, MIN, MAX) on a table column. Supports GROUP BY for breakdowns.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'table' => [
                    'type' => 'string',
                    'description' => 'Name of the table',
                ],
                'function' => [
                    'type' => 'string',
                    'enum' => ['count', 'sum', 'avg', 'min', 'max'],
                    'description' => 'Aggregate function to apply',
                ],
                'column' => [
                    'type' => 'string',
                    'description' => 'Column to aggregate (can be * for COUNT)',
                ],
                'where' => [
                    'type' => 'object',
                    'description' => 'Optional filter conditions',
                ],
                'group_by' => [
                    'type' => 'string',
                    'description' => 'Optional column to group results by',
                ],
            ],
            'required' => ['table', 'function'],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function execute(array $params): ToolResult
    {
        $table = $params['table'] ?? '';
        $function = strtolower($params['function'] ?? 'count');
        $column = $params['column'] ?? '*';
        $where = $params['where'] ?? [];
        $groupBy = $params['group_by'] ?? null;

        $modelClass = $this->validateTable($table);
        if ($modelClass instanceof ToolResult) {
            return $modelClass;
        }

        try {
            $tableName = (new $modelClass())->getTable();
            $query = DB::table($tableName);

            // Apply where conditions
            foreach ($where as $col => $value) {
                if (is_string($value) && str_contains($value, '%')) {
                    $query->where($col, 'LIKE', $value);
                } else {
                    $query->where($col, $value);
                }
            }

            if ($groupBy) {
                // Grouped aggregate
                $columnExpr = $column === '*' ? '*' : $column;
                $results = $query
                    ->select($groupBy)
                    ->selectRaw("{$function}({$columnExpr}) as value")
                    ->groupBy($groupBy)
                    ->orderByDesc('value')
                    ->limit(20)
                    ->get();

                return ToolResult::ok(
                    "{$function}({$column}) of '{$table}' grouped by '{$groupBy}'",
                    $results->toArray()
                );
            } else {
                // Simple aggregate
                $result = match ($function) {
                    'count' => $query->count($column === '*' ? null : $column),
                    'sum' => $query->sum($column),
                    'avg' => round($query->avg($column), 2),
                    'min' => $query->min($column),
                    'max' => $query->max($column),
                    default => null,
                };

                return ToolResult::ok(
                    strtoupper($function) . "({$column}) of '{$table}' = {$result}",
                    ['result' => $result]
                );
            }
        } catch (\Throwable $e) {
            return ToolResult::fail("Aggregate failed: {$e->getMessage()}");
        }
    }
}
