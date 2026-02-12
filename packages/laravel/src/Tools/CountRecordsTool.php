<?php

declare(strict_types=1);

namespace Botovis\Laravel\Tools;

use Botovis\Core\Tools\ToolResult;

/**
 * Count records in a table with optional filters.
 *
 * Use this when you need to know "how many" without fetching all data.
 */
class CountRecordsTool extends BaseTool
{
    public function name(): string
    {
        return 'count_records';
    }

    public function description(): string
    {
        return 'Count the number of records in a table, optionally filtered by conditions.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'table' => [
                    'type' => 'string',
                    'description' => 'Name of the table to count',
                ],
                'where' => [
                    'type' => 'object',
                    'description' => 'Filter conditions as column:value pairs',
                ],
            ],
            'required' => ['table'],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    public function execute(array $params): ToolResult
    {
        $table = $params['table'] ?? '';
        $where = $params['where'] ?? [];

        $modelClass = $this->validateTable($table);
        if ($modelClass instanceof ToolResult) {
            return $modelClass;
        }

        try {
            $query = $this->buildQuery($modelClass, $where);
            $count = $query->count();

            $filterDesc = $where ? " matching the given filters" : "";
            return ToolResult::ok(
                "Table '{$table}' has {$count} record(s){$filterDesc}.",
                ['count' => $count]
            );
        } catch (\Throwable $e) {
            return ToolResult::fail("Count failed: {$e->getMessage()}");
        }
    }
}
