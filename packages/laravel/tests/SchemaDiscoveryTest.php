<?php

declare(strict_types=1);

namespace Botovis\Laravel\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Botovis\Core\Enums\ActionType;
use Botovis\Laravel\Schema\EloquentSchemaDiscovery;

// --- Test Models ---

class FakeProduct extends Model
{
    protected $table = 'products';
    protected $fillable = ['name', 'price', 'category_id'];
    protected $casts = ['price' => 'decimal:2'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FakeCategory::class, 'category_id');
    }
}

class FakeCategory extends Model
{
    protected $table = 'categories';
    protected $fillable = ['name'];

    public function products(): HasMany
    {
        return $this->hasMany(FakeProduct::class, 'category_id');
    }
}

// --- Tests ---

class SchemaDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('categories', function ($table) {
            $table->id();
            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::create('products', function ($table) {
            $table->id();
            $table->string('name', 255);
            $table->decimal('price', 10, 2);
            $table->foreignId('category_id')->constrained();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function test_discovers_table_columns(): void
    {
        $discovery = new EloquentSchemaDiscovery([
            FakeProduct::class => ['create', 'read'],
        ]);

        $schema = $discovery->discover();

        $this->assertCount(1, $schema->tables);

        $table = $schema->findTable('products');
        $this->assertNotNull($table);
        $this->assertEquals('products', $table->name);
        $this->assertEquals(FakeProduct::class, $table->modelClass);

        // Check columns exist
        $colNames = array_map(fn ($c) => $c->name, $table->columns);
        $this->assertContains('id', $colNames);
        $this->assertContains('name', $colNames);
        $this->assertContains('price', $colNames);
        $this->assertContains('category_id', $colNames);
    }

    public function test_discovers_fillable(): void
    {
        $discovery = new EloquentSchemaDiscovery([
            FakeProduct::class => ['create', 'read'],
        ]);

        $schema = $discovery->discover();
        $table = $schema->findTable('products');

        $this->assertEquals(['name', 'price', 'category_id'], $table->fillable);
    }

    public function test_discovers_relations(): void
    {
        $discovery = new EloquentSchemaDiscovery([
            FakeProduct::class => ['read'],
        ]);

        $schema = $discovery->discover();
        $table = $schema->findTable('products');

        $this->assertNotEmpty($table->relations);
        $relNames = array_map(fn ($r) => $r->name, $table->relations);
        $this->assertContains('category', $relNames);
    }

    public function test_parses_allowed_actions(): void
    {
        $discovery = new EloquentSchemaDiscovery([
            FakeProduct::class => ['create', 'read'],
        ]);

        $schema = $discovery->discover();
        $table = $schema->findTable('products');

        $this->assertTrue($table->isActionAllowed(ActionType::CREATE));
        $this->assertTrue($table->isActionAllowed(ActionType::READ));
        $this->assertFalse($table->isActionAllowed(ActionType::DELETE));
    }

    public function test_ignores_nonexistent_model(): void
    {
        $discovery = new EloquentSchemaDiscovery([
            'App\\Models\\NonExistent' => ['read'],
        ]);

        $schema = $discovery->discover();

        $this->assertCount(0, $schema->tables);
    }

    public function test_multiple_models(): void
    {
        $discovery = new EloquentSchemaDiscovery([
            FakeProduct::class => ['create', 'read', 'update', 'delete'],
            FakeCategory::class => ['read'],
        ]);

        $schema = $discovery->discover();

        $this->assertCount(2, $schema->tables);
        $this->assertNotNull($schema->findTable('products'));
        $this->assertNotNull($schema->findTable('categories'));
    }

    public function test_prompt_context_output(): void
    {
        $discovery = new EloquentSchemaDiscovery([
            FakeProduct::class => ['create', 'read'],
        ]);

        $schema = $discovery->discover();
        $context = $schema->toPromptContext();

        $this->assertStringContainsString('products', $context);
        $this->assertStringContainsString('name', $context);
        $this->assertStringContainsString('create', $context);
    }

    public function test_discover_command_with_models(): void
    {
        config(['botovis.models' => [
            FakeProduct::class => ['create', 'read'],
        ]]);

        // Re-register with new config
        $this->app->singleton(\Botovis\Core\Contracts\SchemaDiscoveryInterface::class, function () {
            return new EloquentSchemaDiscovery(config('botovis.models'));
        });

        $this->artisan('botovis:discover')
            ->expectsOutputToContain('products')
            ->assertSuccessful();
    }

    public function test_discover_command_json(): void
    {
        config(['botovis.models' => [
            FakeProduct::class => ['create', 'read'],
        ]]);

        $this->app->singleton(\Botovis\Core\Contracts\SchemaDiscoveryInterface::class, function () {
            return new EloquentSchemaDiscovery(config('botovis.models'));
        });

        $this->artisan('botovis:discover --json')
            ->assertSuccessful();
    }
}
