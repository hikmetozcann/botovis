<?php

declare(strict_types=1);

namespace Botovis\Laravel\Tools;

use Botovis\Core\Tools\ToolResult;

/**
 * Update existing records in a table.
 *
 * This is a write operation that requires user confirmation.
 */
class UpdateRecordTool extends BaseTool
{
    public function name(): string
    {
        return 'update_record';
    }

    public function description(): string
    {
        return 'Update one or more records in a table. Requires user confirmation before execution.';
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
                    'description' => 'Conditions to identify which records to update',
                ],
                'data' => [
                    'type' => 'object',
                    'description' => 'Column:value pairs to update',
                ],
            ],
            'required' => ['table', 'where', 'data'],
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
        $data = $params['data'] ?? [];

        if (empty($where)) {
            return ToolResult::fail("Update requires 'where' conditions to identify records. Cannot update all records.");
        }

        if (empty($data)) {
            return ToolResult::fail("No data provided for update.");
        }

        $modelClass = $this->validateTable($table);
        if ($modelClass instanceof ToolResult) {
            return $modelClass;
        }

        try {
            // Get fillable columns
            $model = new $modelClass();
            $fillable = $model->getFillable();

            // Filter to only fillable columns
            if (!empty($fillable)) {
                $data = array_intersect_key($data, array_flip($fillable));
            }

            if (empty($data)) {
                return ToolResult::fail("None of the provided columns are fillable.");
            }

            $query = $this->buildQuery($modelClass, $where);
            $records = $query->get();

            if ($records->isEmpty()) {
                return ToolResult::fail("No records found matching the conditions.");
            }

            $count = 0;
            foreach ($records as $record) {
                $record->fill($data);
                $record->save();
                $count++;
            }

            return ToolResult::ok(
                "'{$table}' tablosunda {$count} kay\u0131t g\u00fcncellendi.",
                $records->fresh()->toArray(),
                ['count' => $count]
            );
        } catch (\Throwable $e) {
            return ToolResult::fail($this->sanitizeError($e));
        }
    }
}
