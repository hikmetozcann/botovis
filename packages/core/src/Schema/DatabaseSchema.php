<?php

declare(strict_types=1);

namespace Botovis\Core\Schema;

/**
 * Represents the entire discovered database schema.
 * This is the complete picture that gets sent to the LLM as context.
 */
final class DatabaseSchema
{
    /**
     * @param TableSchema[] $tables
     */
    public function __construct(
        public readonly array $tables = [],
    ) {}

    /**
     * Find a table schema by name.
     */
    public function findTable(string $name): ?TableSchema
    {
        foreach ($this->tables as $table) {
            if ($table->name === $name) {
                return $table;
            }
        }
        return null;
    }

    /**
     * Find a table schema by its model class name.
     */
    public function findByModel(string $modelClass): ?TableSchema
    {
        foreach ($this->tables as $table) {
            if ($table->modelClass === $modelClass) {
                return $table;
            }
        }
        return null;
    }

    /**
     * Get all table names.
     *
     * @return string[]
     */
    public function getTableNames(): array
    {
        return array_map(fn (TableSchema $t) => $t->name, $this->tables);
    }

    /**
     * Convert to array for LLM context.
     */
    public function toArray(): array
    {
        return [
            'tables' => array_map(fn (TableSchema $t) => $t->toArray(), $this->tables),
        ];
    }

    /**
     * Build a compact text summary for LLM system prompt.
     */
    public function toPromptContext(): string
    {
        $lines = ["DATABASE SCHEMA:"];

        foreach ($this->tables as $table) {
            $actions = implode(', ', array_map(fn ($a) => $a->value, $table->allowedActions));
            $lines[] = "\n## {$table->label} (table: {$table->name}) [actions: {$actions}]";

            foreach ($table->columns as $col) {
                $flags = [];
                if ($col->isPrimary) $flags[] = 'PK';
                if ($col->nullable) $flags[] = 'nullable';
                if ($col->maxLength) $flags[] = "max:{$col->maxLength}";
                if ($col->enumValues) $flags[] = 'values:' . implode('|', $col->enumValues);

                $flagStr = $flags ? ' (' . implode(', ', $flags) . ')' : '';
                $lines[] = "  - {$col->name}: {$col->type->value}{$flagStr}";
            }

            if (!empty($table->relations)) {
                $lines[] = "  Relations:";
                foreach ($table->relations as $rel) {
                    $lines[] = "    - {$rel->name} → {$rel->relatedTable} ({$rel->type->value})";
                }
            }
        }

        return implode("\n", $lines);
    }
}
