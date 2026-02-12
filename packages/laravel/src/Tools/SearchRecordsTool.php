<?php

declare(strict_types=1);

namespace Botovis\Laravel\Tools;

use Botovis\Core\Tools\ToolResult;

/**
 * Search for records in a table with filters.
 *
 * This is the primary tool for querying data.
 */
class SearchRecordsTool extends BaseTool
{
    public function name(): string
    {
        return 'search_records';
    }

    public function description(): string
    {
        return 'Search for records in a database table. Returns matching records with optional filters, sorting, and column selection.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'table' => [
                    'type' => 'string',
                    'description' => 'Name of the table to search',
                ],
                'where' => [
                    'type' => 'object',
                    'description' => 'Filter conditions as column:value pairs. Use % for LIKE patterns. Use [">", 100] for operators.',
                ],
                'select' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Columns to return (empty for all)',
                ],
                'order_by' => [
                    'type' => 'string',
                    'description' => 'Column to sort by',
                ],
                'order_dir' => [
                    'type' => 'string',
                    'enum' => ['asc', 'desc'],
                    'description' => 'Sort direction',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of records to return (default: 20)',
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
        $select = $params['select'] ?? [];
        $orderBy = $params['order_by'] ?? null;
        $orderDir = $params['order_dir'] ?? 'asc';
        $limit = min($params['limit'] ?? 20, 100);

        $modelClass = $this->validateTable($table);
        if ($modelClass instanceof ToolResult) {
            return $modelClass;
        }

        try {
            $query = $this->buildQuery($modelClass, $where);

            if (!empty($select)) {
                $query->select(array_unique(array_merge(['id'], $select)));
            }

            if ($orderBy) {
                $query->orderBy($orderBy, $orderDir);
            }

            $records = $query->limit($limit)->get();

            // Disable appends when using select
            if (!empty($select)) {
                $records->each(fn ($r) => $r->setAppends([]));
            }

            if ($records->isEmpty()) {
                return ToolResult::ok(
                    "No records found in '{$table}'" . ($where ? " matching the filters." : "."),
                    []
                );
            }

            return ToolResult::ok(
                "Found {$records->count()} record(s) in '{$table}'.",
                $records->toArray(),
                ['total' => $records->count()]
            );
        } catch (\Throwable $e) {
            return ToolResult::fail("Search failed: {$e->getMessage()}");
        }
    }
}
