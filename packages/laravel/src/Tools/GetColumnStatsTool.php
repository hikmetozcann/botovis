<?php

declare(strict_types=1);

namespace Botovis\Laravel\Tools;

use Botovis\Core\Tools\ToolResult;
use Illuminate\Support\Facades\DB;

/**
 * Get statistics for a column (min, max, avg, distinct values).
 *
 * Use this to understand the range and distribution of values in a column.
 */
class GetColumnStatsTool extends BaseTool
{
    public function name(): string
    {
        return 'get_column_stats';
    }

    public function description(): string
    {
        return 'Get statistics for a column: min, max, average (for numbers), and list of distinct values (for text/enum).';
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
                'column' => [
                    'type' => 'string',
                    'description' => 'Name of the column to analyze',
                ],
            ],
            'required' => ['table', 'column'],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function execute(array $params): ToolResult
    {
        $table = $params['table'] ?? '';
        $column = $params['column'] ?? '';

        $modelClass = $this->validateTable($table);
        if ($modelClass instanceof ToolResult) {
            return $modelClass;
        }

        try {
            $tableName = (new $modelClass())->getTable();

            // Get basic stats
            $stats = DB::table($tableName)
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(DISTINCT {$column}) as distinct_count,
                    MIN({$column}) as min_value,
                    MAX({$column}) as max_value
                ")
                ->first();

            // Try to get average (only works for numeric columns)
            $avg = null;
            try {
                $avg = DB::table($tableName)->avg($column);
            } catch (\Throwable) {
                // Not a numeric column
            }

            // Get distinct values if count is reasonable
            $distinctValues = [];
            if ($stats->distinct_count <= 20) {
                $distinctValues = DB::table($tableName)
                    ->select($column)
                    ->distinct()
                    ->whereNotNull($column)
                    ->orderBy($column)
                    ->pluck($column)
                    ->toArray();
            }

            $result = [
                'column' => $column,
                'total_records' => $stats->total,
                'distinct_count' => $stats->distinct_count,
                'min' => $stats->min_value,
                'max' => $stats->max_value,
            ];

            if ($avg !== null) {
                $result['average'] = round($avg, 2);
            }

            if (!empty($distinctValues)) {
                $result['distinct_values'] = $distinctValues;
            }

            return ToolResult::ok(
                "Statistics for '{$table}.{$column}': {$stats->distinct_count} distinct values, range: {$stats->min_value} to {$stats->max_value}",
                $result
            );
        } catch (\Throwable $e) {
            return ToolResult::fail("Failed to get column stats: {$e->getMessage()}");
        }
    }
}
