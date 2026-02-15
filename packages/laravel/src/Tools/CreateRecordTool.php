<?php

declare(strict_types=1);

namespace Botovis\Laravel\Tools;

use Botovis\Core\Tools\ToolResult;

/**
 * Create a new record in a table.
 *
 * This is a write operation that requires user confirmation.
 */
class CreateRecordTool extends BaseTool
{
    public function name(): string
    {
        return 'create_record';
    }

    public function description(): string
    {
        return 'Create a new record in a table. Requires user confirmation before execution.';
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
                'data' => [
                    'type' => 'object',
                    'description' => 'Column:value pairs for the new record',
                ],
            ],
            'required' => ['table', 'data'],
        ];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function execute(array $params): ToolResult
    {
        $table = $params['table'] ?? '';
        $data = $params['data'] ?? [];

        if (empty($data)) {
            return ToolResult::fail("No data provided for creating record.");
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

            $model->fill($data);
            $model->save();

            return ToolResult::ok(
                "'{$table}' tablosuna yeni kay\u0131t eklendi. ID: {$model->getKey()}",
                $model->toArray(),
                ['id' => $model->getKey()]
            );
        } catch (\Throwable $e) {
            return ToolResult::fail($this->sanitizeError($e));
        }
    }
}
