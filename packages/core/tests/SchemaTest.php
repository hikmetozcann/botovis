<?php

declare(strict_types=1);

namespace Botovis\Core\Tests;

use PHPUnit\Framework\TestCase;
use Botovis\Core\Enums\ActionType;
use Botovis\Core\Enums\ColumnType;
use Botovis\Core\Enums\RelationType;
use Botovis\Core\Schema\ColumnSchema;
use Botovis\Core\Schema\RelationSchema;
use Botovis\Core\Schema\TableSchema;
use Botovis\Core\Schema\DatabaseSchema;
use Botovis\Core\Contracts\ActionResult;

class SchemaTest extends TestCase
{
    public function test_column_schema_to_array(): void
    {
        $col = new ColumnSchema(
            name: 'price',
            type: ColumnType::DECIMAL,
            nullable: false,
            maxLength: null,
        );

        $arr = $col->toArray();
        $this->assertEquals('price', $arr['name']);
        $this->assertEquals('decimal', $arr['type']);
    }

    public function test_table_schema_action_allowed(): void
    {
        $table = new TableSchema(
            name: 'products',
            allowedActions: [ActionType::CREATE, ActionType::READ],
        );

        $this->assertTrue($table->isActionAllowed(ActionType::READ));
        $this->assertFalse($table->isActionAllowed(ActionType::DELETE));
    }

    public function test_table_writable_columns_excludes_primary_and_guarded(): void
    {
        $table = new TableSchema(
            name: 'products',
            columns: [
                new ColumnSchema(name: 'id', type: ColumnType::INTEGER, isPrimary: true),
                new ColumnSchema(name: 'name', type: ColumnType::STRING),
                new ColumnSchema(name: 'price', type: ColumnType::DECIMAL),
                new ColumnSchema(name: 'secret', type: ColumnType::STRING),
            ],
            fillable: ['name', 'price'],
        );

        $writable = $table->getWritableColumns();
        $names = array_map(fn ($c) => $c->name, $writable);

        $this->assertContains('name', $names);
        $this->assertContains('price', $names);
        $this->assertNotContains('id', $names);
        $this->assertNotContains('secret', $names);
    }

    public function test_database_schema_find_table(): void
    {
        $db = new DatabaseSchema([
            new TableSchema(name: 'products'),
            new TableSchema(name: 'categories'),
        ]);

        $this->assertNotNull($db->findTable('products'));
        $this->assertNull($db->findTable('nonexistent'));
        $this->assertEquals(['products', 'categories'], $db->getTableNames());
    }

    public function test_database_schema_prompt_context(): void
    {
        $db = new DatabaseSchema([
            new TableSchema(
                name: 'products',
                label: 'Ürünler',
                allowedActions: [ActionType::CREATE, ActionType::READ],
                columns: [
                    new ColumnSchema(name: 'id', type: ColumnType::INTEGER, isPrimary: true),
                    new ColumnSchema(name: 'name', type: ColumnType::STRING, maxLength: 255),
                ],
                relations: [
                    new RelationSchema(
                        name: 'category',
                        type: RelationType::BELONGS_TO,
                        relatedTable: 'categories',
                        foreignKey: 'category_id',
                    ),
                ],
            ),
        ]);

        $context = $db->toPromptContext();
        $this->assertStringContainsString('Ürünler', $context);
        $this->assertStringContainsString('products', $context);
        $this->assertStringContainsString('name: string', $context);
        $this->assertStringContainsString('category → categories', $context);
    }

    public function test_action_result_ok_and_fail(): void
    {
        $ok = ActionResult::ok('Created', ['id' => 1], 1);
        $this->assertTrue($ok->success);
        $this->assertEquals(1, $ok->affected);

        $fail = ActionResult::fail('Not authorized');
        $this->assertFalse($fail->success);
    }
}
