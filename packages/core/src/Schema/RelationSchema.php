<?php

declare(strict_types=1);

namespace Botovis\Core\Schema;

use Botovis\Core\Enums\RelationType;

/**
 * Represents a relationship between two tables/models.
 */
final class RelationSchema
{
    /**
     * @param string       $name         Relation name (e.g. "comments", "author")
     * @param RelationType $type         Relation type
     * @param string       $relatedTable The related table name
     * @param string       $foreignKey   The foreign key column
     * @param string|null  $localKey     The local key column
     * @param string|null  $pivotTable   Pivot table for many-to-many
     */
    public function __construct(
        public readonly string $name,
        public readonly RelationType $type,
        public readonly string $relatedTable,
        public readonly string $foreignKey,
        public readonly ?string $localKey = null,
        public readonly ?string $pivotTable = null,
    ) {}

    /**
     * Convert to array for LLM context.
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type->value,
            'related_table' => $this->relatedTable,
            'foreign_key' => $this->foreignKey,
            'local_key' => $this->localKey,
            'pivot_table' => $this->pivotTable,
        ], fn ($v) => $v !== null);
    }
}
