<?php

declare(strict_types=1);

namespace Botovis\Laravel\Tools;

use Botovis\Core\Tools\ToolResult;

/**
 * Get sample data from a table.
 *
 * Use this to understand what kind of data is in a table before querying.
 */
class GetSampleDataTool extends BaseTool
{
    public function name(): string
    {
        return 'get_sample_data';
    }

    public function description(): string
    {
        return 'Get a few sample records from a table to understand its data structure and content.';
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
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Number of samples (default: 3, max: 5)',
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
        $limit = min($params['limit'] ?? 3, 5);

        $modelClass = $this->validateTable($table);
        if ($modelClass instanceof ToolResult) {
            return $modelClass;
        }

        try {
            $records = $modelClass::query()
                ->inRandomOrder()
                ->limit($limit)
                ->get();

            if ($records->isEmpty()) {
                return ToolResult::ok(
                    "Table '{$table}' is empty.",
                    []
                );
            }

            // Get column info from first record
            $columns = array_keys($records->first()->toArray());

            return ToolResult::ok(
                "Sample data from '{$table}' ({$records->count()} records). Columns: " . implode(', ', $columns),
                $records->toArray()
            );
        } catch (\Throwable $e) {
            return ToolResult::fail("Failed to get sample data: {$e->getMessage()}");
        }
    }
}
