<?php

declare(strict_types=1);

namespace Botovis\Laravel\Tools;

use Botovis\Core\Tools\ToolResult;

/**
 * Delete records from a table.
 *
 * This is a write operation that requires user confirmation.
 */
class DeleteRecordTool extends BaseTool
{
    public function name(): string
    {
        return 'delete_record';
    }

    public function description(): string
    {
        return 'Delete one or more records from a table. Requires user confirmation before execution.';
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
                'where' => [
                    'type' => 'object',
                    'description' => 'Conditions to identify which records to delete',
                ],
            ],
            'required' => ['table', 'where'],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function execute(array $params): ToolResult
    {
        $table = $params['table'] ?? '';
        $where = $params['where'] ?? [];

        if (empty($where)) {
            return ToolResult::fail("Delete requires 'where' conditions. Cannot delete all records.");
        }

        $modelClass = $this->validateTable($table);
        if ($modelClass instanceof ToolResult) {
            return $modelClass;
        }

        try {
            $query = $this->buildQuery($modelClass, $where);
            $records = $query->get();

            if ($records->isEmpty()) {
                return ToolResult::fail("No records found matching the conditions.");
            }

            $count = $records->count();
            $deletedIds = $records->pluck('id')->toArray();

            foreach ($records as $record) {
                $record->delete();
            }

            return ToolResult::ok(
                "'{$table}' tablosundan {$count} kay\u0131t silindi.",
                ['deleted_ids' => $deletedIds],
                ['count' => $count]
            );
        } catch (\Throwable $e) {
            return ToolResult::fail($this->sanitizeError($e));
        }
    }
}
