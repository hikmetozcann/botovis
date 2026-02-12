<?php

declare(strict_types=1);

namespace Botovis\Laravel\Tools;

use Botovis\Core\Schema\DatabaseSchema;
use Botovis\Core\Tools\ToolInterface;
use Botovis\Core\Tools\ToolResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for Laravel/Eloquent tools.
 */
abstract class BaseTool implements ToolInterface
{
    public function __construct(
        protected readonly DatabaseSchema $schema,
    ) {}

    /**
     * Get the Eloquent model class for a table.
     */
    protected function getModelClass(string $table): ?string
    {
        $tableSchema = $this->schema->findTable($table);
        return $tableSchema?->modelClass;
    }

    /**
     * Validate that a table exists and return its model class.
     */
    protected function validateTable(string $table): string|ToolResult
    {
        $modelClass = $this->getModelClass($table);

        if (!$modelClass || !class_exists($modelClass)) {
            return ToolResult::fail("Table '{$table}' is not accessible.");
        }

        return $modelClass;
    }

    /**
     * Build a query with where conditions.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildQuery(string $modelClass, array $where = [])
    {
        $query = $modelClass::query();

        foreach ($where as $column => $value) {
            if (is_array($value)) {
                // Handle operators: ['column' => ['>=', 100]]
                if (count($value) === 2 && is_string($value[0])) {
                    $query->where($column, $value[0], $value[1]);
                } else {
                    $query->whereIn($column, $value);
                }
            } elseif (is_string($value) && str_contains($value, '%')) {
                $query->where($column, 'LIKE', $value);
            } else {
                $query->where($column, $value);
            }
        }

        return $query;
    }
}
