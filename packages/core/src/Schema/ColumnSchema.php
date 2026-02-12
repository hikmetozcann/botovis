<?php

declare(strict_types=1);

namespace Botovis\Core\Schema;

use Botovis\Core\Enums\ColumnType;

/**
 * Represents a single column in a database table.
 */
final class ColumnSchema
{
    /**
     * @param string      $name       Column name
     * @param ColumnType  $type       Normalized data type
     * @param bool        $nullable   Whether the column accepts NULL
     * @param bool        $isPrimary  Whether this is a primary key
     * @param mixed       $default    Default value (if any)
     * @param int|null    $maxLength  Max length for string types
     * @param string[]    $enumValues Possible values for ENUM types
     * @param string[]    $rules      Validation rules discovered from ORM
     */
    public function __construct(
        public readonly string $name,
        public readonly ColumnType $type,
        public readonly bool $nullable = false,
        public readonly bool $isPrimary = false,
        public readonly mixed $default = null,
        public readonly ?int $maxLength = null,
        public readonly array $enumValues = [],
        public readonly array $rules = [],
    ) {}

    /**
     * Convert to array for LLM context.
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type->value,
            'nullable' => $this->nullable,
            'is_primary' => $this->isPrimary,
            'default' => $this->default,
            'max_length' => $this->maxLength,
            'enum_values' => $this->enumValues ?: null,
            'rules' => $this->rules ?: null,
        ], fn ($v) => $v !== null && $v !== false);
    }
}
