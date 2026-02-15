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
     * Sanitize database error messages for user display.
     * Removes SQL queries, connection details, and technical info.
     */
    protected function sanitizeError(\Throwable $e): string
    {
        $message = $e->getMessage();

        // Duplicate entry
        if (preg_match('/Duplicate entry .+? for key .+?\.(\w+)/i', $message, $m)) {
            $key = $m[1];
            // Strip table prefix (e.g. "employees_tc_no_unique" → "tc_no_unique")
            $key = preg_replace('/^\w+?_/', '', $key, 1);
            $key = str_replace('_unique', '', $key);
            $key = str_replace('_', ' ', $key);
            return "Bu kay\u0131t zaten mevcut. '{$key}' alan\u0131 benzersiz olmal\u0131d\u0131r.";
        }

        // Not null violation
        if (preg_match('/Column .+?([\'\"])(\w+)\1.+?cannot be null/i', $message, $m)) {
            return "'{$m[2]}' alan\u0131 bo\u015f b\u0131rak\u0131lamaz.";
        }

        // Foreign key constraint
        if (str_contains($message, 'foreign key constraint')) {
            return 'Bu i\u015flem ba\u011fl\u0131 kay\u0131tlar nedeniyle ger\u00e7ekle\u015ftirilemiyor.';
        }

        // Data too long
        if (preg_match('/Data too long for column .+?([\'\"])(\w+)\1/i', $message, $m)) {
            return "'{$m[2]}' alan\u0131na girilen de\u011fer \u00e7ok uzun.";
        }

        // Generic SQL error - hide details
        if (str_contains($message, 'SQLSTATE')) {
            return 'Veritaban\u0131 i\u015flemi s\u0131ras\u0131nda bir hata olu\u015ftu.';
        }

        // Non-SQL errors, return as is (capped at 200 chars)
        return mb_substr($message, 0, 200);
    }

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
