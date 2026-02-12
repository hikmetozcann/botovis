<?php

declare(strict_types=1);

namespace Botovis\Core\Schema;

use Botovis\Core\Enums\ActionType;

/**
 * Represents a single table/model schema with its columns, relations, and permissions.
 */
final class TableSchema
{
    /**
     * @param string           $name           Table name
     * @param string|null      $modelClass     Full class name of the ORM model (if available)
     * @param string|null      $label          Human-readable label (e.g. "Products", "Ürünler")
     * @param ColumnSchema[]   $columns        Table columns
     * @param RelationSchema[] $relations      Table relationships
     * @param ActionType[]     $allowedActions Actions Botovis is permitted to do
     * @param string[]         $fillable       Fillable/mass-assignable fields
     * @param string[]         $guarded        Guarded fields
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $modelClass = null,
        public readonly ?string $label = null,
        public readonly array $columns = [],
        public readonly array $relations = [],
        public readonly array $allowedActions = [],
        public readonly array $fillable = [],
        public readonly array $guarded = [],
    ) {}

    /**
     * Check if a given action is allowed on this table.
     */
    public function isActionAllowed(ActionType $action): bool
    {
        return in_array($action, $this->allowedActions, true);
    }

    /**
     * Get columns that can be written to (fillable, non-primary, non-guarded).
     *
     * @return ColumnSchema[]
     */
    public function getWritableColumns(): array
    {
        return array_filter($this->columns, function (ColumnSchema $col) {
            if ($col->isPrimary) {
                return false;
            }
            if (!empty($this->fillable) && !in_array($col->name, $this->fillable, true)) {
                return false;
            }
            if (in_array($col->name, $this->guarded, true)) {
                return false;
            }
            return true;
        });
    }

    /**
     * Convert to array for LLM context.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label ?? $this->name,
            'allowed_actions' => array_map(fn (ActionType $a) => $a->value, $this->allowedActions),
            'columns' => array_map(fn (ColumnSchema $c) => $c->toArray(), $this->columns),
            'relations' => array_map(fn (RelationSchema $r) => $r->toArray(), $this->relations),
            'fillable' => $this->fillable,
        ];
    }
}
