<?php

declare(strict_types=1);

namespace Botovis\Laravel\Commands;

use Illuminate\Console\Command;
use Botovis\Core\Contracts\SchemaDiscoveryInterface;

/**
 * Artisan command to test schema discovery.
 *
 * Usage: php artisan botovis:discover
 *
 * Shows what Botovis "sees" — which models, columns, relations, and permissions.
 * This is the first thing you run after configuring botovis.php.
 */
class DiscoverCommand extends Command
{
    protected $signature = 'botovis:discover {--json : Output as JSON} {--prompt : Output as LLM prompt context}';
    protected $description = 'Discover and display all models visible to Botovis';

    public function handle(SchemaDiscoveryInterface $discovery): int
    {
        $schema = $discovery->discover();

        if (count($schema->tables) === 0) {
            $this->warn('No models configured for Botovis.');
            $this->line('');
            $this->line('Add models to config/botovis.php:');
            $this->line('');
            $this->info("  'models' => [");
            $this->info("      App\\Models\\Product::class => ['create', 'read', 'update', 'delete'],");
            $this->info("  ],");
            return self::SUCCESS;
        }

        // JSON output
        if ($this->option('json')) {
            $this->line(json_encode($schema->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        // LLM prompt context output
        if ($this->option('prompt')) {
            $this->line($schema->toPromptContext());
            return self::SUCCESS;
        }

        // Pretty table output
        $this->info('🔍 Botovis Schema Discovery');
        $this->line('');

        foreach ($schema->tables as $table) {
            $actions = implode(', ', array_map(fn ($a) => $a->value, $table->allowedActions));

            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📋 {$table->label}");
            $this->line("   Table: {$table->name} | Model: {$table->modelClass}");
            $this->line("   Actions: {$actions}");
            $this->line('');

            // Columns
            $this->line('   Columns:');
            foreach ($table->columns as $col) {
                $flags = [];
                if ($col->isPrimary) $flags[] = '<fg=yellow>PK</>';
                if ($col->nullable) $flags[] = '<fg=gray>nullable</>';
                if ($col->maxLength) $flags[] = "<fg=gray>max:{$col->maxLength}</>";

                $fillableMarker = in_array($col->name, $table->fillable) ? '<fg=green>✓</>' : '<fg=red>✗</>';
                $flagStr = $flags ? ' ' . implode(' ', $flags) : '';

                $this->line("   {$fillableMarker} {$col->name}: <fg=cyan>{$col->type->value}</>{$flagStr}");
                
                // Show enum values if present
                if (!empty($col->enumValues)) {
                    $enumStr = implode(', ', array_slice($col->enumValues, 0, 5));
                    if (count($col->enumValues) > 5) {
                        $enumStr .= '... +' . (count($col->enumValues) - 5) . ' more';
                    }
                    $this->line("      <fg=gray>Values: {$enumStr}</>");
                }
            }

            // Relations
            if (!empty($table->relations)) {
                $this->line('');
                $this->line('   Relations:');
                foreach ($table->relations as $rel) {
                    $this->line("   → {$rel->name}: {$rel->relatedTable} (<fg=magenta>{$rel->type->value}</>)");
                }
            }

            $this->line('');
        }

        $this->info("Total: " . count($schema->tables) . " model(s) visible to Botovis");

        return self::SUCCESS;
    }
}
