<?php

declare(strict_types=1);

namespace Botovis\Laravel\Schema;

use Botovis\Core\Contracts\SchemaDiscoveryInterface;
use Botovis\Core\Enums\ActionType;
use Botovis\Core\Enums\ColumnType;
use Botovis\Core\Enums\RelationType;
use Botovis\Core\Schema\ColumnSchema;
use Botovis\Core\Schema\DatabaseSchema;
use Botovis\Core\Schema\RelationSchema;
use Botovis\Core\Schema\TableSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations;
use Illuminate\Support\Facades\Schema;

/**
 * Discovers the database schema by reading Eloquent models.
 *
 * Combines ORM reflection (fillable, casts, relations) with
 * actual DB introspection (column types, nullable, etc.)
 */
class EloquentSchemaDiscovery implements SchemaDiscoveryInterface
{
    /**
     * Common method patterns that return enum/option values
     */
    private const ENUM_METHOD_PATTERNS = [
        'Options',   // statusOptions(), typeOptions()
        'Labels',    // statusLabels(), typeLabels()
        'Values',    // statusValues()
        'Choices',   // statusChoices()
        'List',      // statusList()
    ];

    /**
     * @param array<class-string<Model>, string[]> $modelConfig
     *        e.g. [Product::class => ['create', 'read', 'update']]
     */
    public function __construct(
        private readonly array $modelConfig = [],
    ) {}

    /**
     * Discover and return the full database schema.
     */
    public function discover(): DatabaseSchema
    {
        $tables = [];

        foreach ($this->modelConfig as $modelClass => $actions) {
            if (!class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass();

            if (!$model instanceof Model) {
                continue;
            }

            $tables[] = $this->discoverModel($model, $modelClass, $actions);
        }

        return new DatabaseSchema($tables);
    }

    /**
     * Discover a single Eloquent model.
     */
    private function discoverModel(Model $model, string $modelClass, array $actions): TableSchema
    {
        $tableName = $model->getTable();

        // Discover enum values from model methods
        $enumValues = $this->discoverEnumValues($model, $modelClass);

        return new TableSchema(
            name: $tableName,
            modelClass: $modelClass,
            label: $this->guessLabel($modelClass),
            columns: $this->discoverColumns($model, $tableName, $enumValues),
            relations: $this->discoverRelations($model),
            allowedActions: $this->parseActions($actions),
            fillable: $model->getFillable(),
            guarded: $model->getGuarded(),
        );
    }

    /**
     * Discover enum values from model static methods like statusOptions(), typeLabels(), etc.
     *
     * @return array<string, string[]> Column name => possible values
     */
    private function discoverEnumValues(Model $model, string $modelClass): array
    {
        $enumValues = [];
        $ref = new \ReflectionClass($modelClass);

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_STATIC) as $method) {
            if ($method->class !== $modelClass) continue;
            if ($method->getNumberOfRequiredParameters() > 0) continue;

            $methodName = $method->getName();
            
            // Check if method matches patterns like statusOptions, typeLabels, etc.
            foreach (self::ENUM_METHOD_PATTERNS as $pattern) {
                if (str_ends_with($methodName, $pattern)) {
                    $columnName = lcfirst(str_replace($pattern, '', $methodName));
                    if (empty($columnName)) continue;

                    try {
                        $result = $model::$methodName();
                        $values = $this->extractEnumValuesFromResult($result);
                        if (!empty($values)) {
                            $enumValues[$columnName] = $values;
                        }
                    } catch (\Throwable) {
                        continue;
                    }
                    break;
                }
            }
        }

        return $enumValues;
    }

    /**
     * Extract enum values from various result formats
     */
    private function extractEnumValuesFromResult(mixed $result): array
    {
        if (!is_array($result)) {
            return [];
        }

        // Format: ['value' => 'label', ...]
        if (array_is_list($result) === false) {
            return array_keys($result);
        }

        // Format: [['value' => 'x', 'label' => 'X'], ...]
        $values = [];
        foreach ($result as $item) {
            if (isset($item['value'])) {
                $values[] = (string) $item['value'];
            } elseif (is_string($item) || is_int($item)) {
                $values[] = (string) $item;
            }
        }
        return $values;
    }

    /**
     * Discover columns from the actual database table + Eloquent casts.
     *
     * @param array<string, string[]> $enumValues  Column name => possible values (from model methods)
     * @return ColumnSchema[]
     */
    private function discoverColumns(Model $model, string $tableName, array $enumValues = []): array
    {
        $columns = [];

        if (!Schema::hasTable($tableName)) {
            return $columns;
        }

        $dbColumns = Schema::getColumns($tableName);
        $casts = $model->getCasts();
        $primaryKey = $model->getKeyName();

        foreach ($dbColumns as $dbCol) {
            $name = $dbCol['name'];
            $colType = $this->mapColumnType($dbCol['type_name'], $casts[$name] ?? null);
            
            // Get enum values: first from model methods, then from DB if it's an enum column
            $colEnumValues = $enumValues[$name] ?? [];
            if (empty($colEnumValues) && $colType === ColumnType::ENUM) {
                $colEnumValues = $this->extractDbEnumValues($dbCol['type'] ?? '');
            }

            // If we have enum values but type is string, upgrade to ENUM type
            if (!empty($colEnumValues) && $colType === ColumnType::STRING) {
                $colType = ColumnType::ENUM;
            }

            $columns[] = new ColumnSchema(
                name: $name,
                type: $colType,
                nullable: $dbCol['nullable'] ?? false,
                isPrimary: $name === $primaryKey,
                default: $dbCol['default'] ?? null,
                maxLength: $this->extractMaxLength($dbCol['type'] ?? ''),
                enumValues: $colEnumValues,
            );
        }

        return $columns;
    }

    /**
     * Extract enum values from database ENUM column definition
     * e.g. "enum('active','inactive','pending')" → ['active', 'inactive', 'pending']
     */
    private function extractDbEnumValues(string $typeDefinition): array
    {
        if (!preg_match("/^enum\s*\((.+)\)$/i", $typeDefinition, $matches)) {
            return [];
        }
        
        // Parse the values from 'val1','val2','val3'
        preg_match_all("/'([^']+)'/", $matches[1], $valueMatches);
        return $valueMatches[1] ?? [];
    }

    /**
     * Discover relationships by inspecting the model's methods.
     *
     * @return RelationSchema[]
     */
    private function discoverRelations(Model $model): array
    {
        $relations = [];
        $ref = new \ReflectionClass($model);

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip non-model methods
            if ($method->class !== get_class($model)) {
                continue;
            }

            if ($method->getNumberOfParameters() > 0) {
                continue;
            }

            // Check return type hint
            $returnType = $method->getReturnType();
            if (!$returnType instanceof \ReflectionNamedType) {
                continue;
            }

            $typeName = $returnType->getName();
            $relationType = $this->mapRelationType($typeName);

            if ($relationType === null) {
                continue;
            }

            try {
                $relation = $model->{$method->getName()}();

                if (!$relation instanceof Relations\Relation) {
                    continue;
                }

                $relatedModel = $relation->getRelated();

                $schema = new RelationSchema(
                    name: $method->getName(),
                    type: $relationType,
                    relatedTable: $relatedModel->getTable(),
                    foreignKey: $this->extractForeignKey($relation),
                    localKey: $this->extractLocalKey($relation),
                    pivotTable: $this->extractPivotTable($relation),
                );

                $relations[] = $schema;
            } catch (\Throwable) {
                // Skip relations that can't be instantiated
                continue;
            }
        }

        return $relations;
    }

    /**
     * Map a database column type to our normalized ColumnType.
     */
    private function mapColumnType(string $dbType, ?string $cast): ColumnType
    {
        // Eloquent cast takes priority
        if ($cast !== null) {
            return match (true) {
                $cast === 'boolean', $cast === 'bool' => ColumnType::BOOLEAN,
                $cast === 'integer', $cast === 'int' => ColumnType::INTEGER,
                $cast === 'float', $cast === 'double', $cast === 'real' => ColumnType::FLOAT,
                $cast === 'decimal' => ColumnType::DECIMAL,
                $cast === 'date' => ColumnType::DATE,
                $cast === 'datetime', $cast === 'immutable_datetime' => ColumnType::DATETIME,
                $cast === 'timestamp' => ColumnType::TIMESTAMP,
                str_starts_with($cast, 'decimal:') => ColumnType::DECIMAL,
                $cast === 'array', $cast === 'json', $cast === 'object', $cast === 'collection' => ColumnType::JSON,
                default => $this->mapRawDbType($dbType),
            };
        }

        return $this->mapRawDbType($dbType);
    }

    /**
     * Map raw DB type string to ColumnType.
     */
    private function mapRawDbType(string $dbType): ColumnType
    {
        $dbType = strtolower($dbType);

        return match (true) {
            str_contains($dbType, 'int') => ColumnType::INTEGER,
            str_contains($dbType, 'varchar'), str_contains($dbType, 'char') => ColumnType::STRING,
            str_contains($dbType, 'text') => ColumnType::TEXT,
            str_contains($dbType, 'decimal'), str_contains($dbType, 'numeric') => ColumnType::DECIMAL,
            str_contains($dbType, 'float'), str_contains($dbType, 'double'), str_contains($dbType, 'real') => ColumnType::FLOAT,
            str_contains($dbType, 'bool') => ColumnType::BOOLEAN,
            str_contains($dbType, 'datetime'), str_contains($dbType, 'timestamp') => ColumnType::DATETIME,
            str_contains($dbType, 'date') => ColumnType::DATE,
            str_contains($dbType, 'time') => ColumnType::TIME,
            str_contains($dbType, 'json') => ColumnType::JSON,
            str_contains($dbType, 'enum') => ColumnType::ENUM,
            str_contains($dbType, 'blob'), str_contains($dbType, 'binary') => ColumnType::BINARY,
            str_contains($dbType, 'uuid') => ColumnType::UUID,
            default => ColumnType::UNKNOWN,
        };
    }

    /**
     * Map a relationship class name to our RelationType enum.
     */
    private function mapRelationType(string $className): ?RelationType
    {
        return match ($className) {
            Relations\HasOne::class => RelationType::HAS_ONE,
            Relations\HasMany::class => RelationType::HAS_MANY,
            Relations\BelongsTo::class => RelationType::BELONGS_TO,
            Relations\BelongsToMany::class => RelationType::BELONGS_TO_MANY,
            Relations\MorphOne::class => RelationType::MORPH_ONE,
            Relations\MorphMany::class => RelationType::MORPH_MANY,
            Relations\MorphTo::class => RelationType::MORPH_TO,
            default => null,
        };
    }

    /**
     * Parse action strings to ActionType enums.
     *
     * @return ActionType[]
     */
    private function parseActions(array $actions): array
    {
        return array_filter(array_map(
            fn (string $a) => ActionType::tryFrom($a),
            $actions,
        ));
    }

    /**
     * Guess a human-readable label from the model class name.
     */
    private function guessLabel(string $modelClass): string
    {
        $shortName = class_basename($modelClass);

        // Convert CamelCase to words: "ProductCategory" → "Product Categories"
        $words = preg_replace('/([a-z])([A-Z])/', '$1 $2', $shortName);

        return str($words)->plural()->title()->toString();
    }

    /**
     * Extract max length from a type definition like "varchar(255)".
     */
    private function extractMaxLength(string $fullType): ?int
    {
        if (preg_match('/\((\d+)\)/', $fullType, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    private function extractForeignKey(Relations\Relation $relation): string
    {
        if (method_exists($relation, 'getForeignKeyName')) {
            return $relation->getForeignKeyName();
        }
        if (method_exists($relation, 'getForeignPivotKeyName')) {
            return $relation->getForeignPivotKeyName();
        }
        return '';
    }

    private function extractLocalKey(Relations\Relation $relation): ?string
    {
        if (method_exists($relation, 'getLocalKeyName')) {
            return $relation->getLocalKeyName();
        }
        return null;
    }

    private function extractPivotTable(Relations\Relation $relation): ?string
    {
        if ($relation instanceof Relations\BelongsToMany) {
            return $relation->getTable();
        }
        return null;
    }
}
