<?php

declare(strict_types=1);

namespace Botovis\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Scan the project for Eloquent models and generate config for botovis.php.
 *
 * Usage:
 *   php artisan botovis:models                    — interactive selection, outputs snippet
 *   php artisan botovis:models --all              — select all models with all permissions
 *   php artisan botovis:models --write             — write directly to config/botovis.php
 *   php artisan botovis:models --path=src/Models  — scan a custom directory
 */
class ModelsCommand extends Command
{
    protected $signature = 'botovis:models
        {--all : Select all discovered models with full CRUD permissions}
        {--write : Write selected models directly to config/botovis.php}
        {--read-only : Default all models to read-only permissions}
        {--path= : Custom path to scan for models (relative to base_path)}';

    protected $description = 'Discover Eloquent models and generate Botovis config';

    private const ACTIONS = ['create', 'read', 'update', 'delete'];

    public function handle(): int
    {
        $this->info('🔍 Scanning for Eloquent models...');
        $this->line('');

        $models = $this->discoverModels();

        if (empty($models)) {
            $this->warn('No Eloquent models found.');
            $this->line('');
            $this->line('Make sure your models extend Illuminate\Database\Eloquent\Model.');
            $this->line('You can also specify a custom path: --path=src/Models');
            return self::SUCCESS;
        }

        $this->info("Found <fg=cyan>" . count($models) . "</> model(s):");
        $this->line('');

        foreach ($models as $i => $model) {
            $this->line("  <fg=gray>" . ($i + 1) . ".</> {$model}");
        }
        $this->line('');

        // Already configured models
        $existingModels = array_keys(config('botovis.models', []));
        if (!empty($existingModels)) {
            $this->line('<fg=yellow>Already configured:</> ' . implode(', ', array_map(
                fn($m) => class_basename($m),
                $existingModels
            )));
            $this->line('');
        }

        // Select models
        $selected = $this->selectModels($models);

        if (empty($selected)) {
            $this->warn('No models selected.');
            return self::SUCCESS;
        }

        // Assign permissions
        $config = $this->assignPermissions($selected);

        // Output
        $snippet = $this->generateSnippet($config);

        if ($this->option('write')) {
            return $this->writeToConfig($config, $snippet);
        }

        $this->outputSnippet($snippet);

        return self::SUCCESS;
    }

    /**
     * Discover all Eloquent models in the project.
     *
     * @return string[] Fully qualified class names
     */
    private function discoverModels(): array
    {
        $path = $this->option('path')
            ? base_path($this->option('path'))
            : app_path('Models');

        if (!is_dir($path)) {
            // Fallback: try app/ directly (older Laravel structure)
            $path = app_path();
            if (!is_dir($path)) {
                return [];
            }
        }

        $models = [];
        $finder = new Finder();
        $finder->files()->name('*.php')->in($path)->sortByName();

        foreach ($finder as $file) {
            $className = $this->resolveClassName($file->getRealPath());

            if ($className === null) {
                continue;
            }

            try {
                if (!class_exists($className)) {
                    continue;
                }

                $reflection = new \ReflectionClass($className);

                if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                    continue;
                }

                if (!$reflection->isSubclassOf(Model::class)) {
                    continue;
                }

                $models[] = $className;
            } catch (\Throwable) {
                // Skip unloadable classes
                continue;
            }
        }

        return $models;
    }

    /**
     * Resolve the fully qualified class name from a PHP file.
     */
    private function resolveClassName(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);

        // Extract namespace
        if (!preg_match('/namespace\s+(.+?)\s*;/', $contents, $nsMatch)) {
            return null;
        }

        // Extract class name
        if (!preg_match('/class\s+(\w+)/', $contents, $classMatch)) {
            return null;
        }

        return $nsMatch[1] . '\\' . $classMatch[1];
    }

    /**
     * Interactive model selection.
     *
     * @param string[] $models
     * @return string[]
     */
    private function selectModels(array $models): array
    {
        if ($this->option('all')) {
            $this->info('--all flag: selecting all models.');
            return $models;
        }

        // Build choice list
        $choices = array_map(fn($m) => class_basename($m) . " ({$m})", $models);

        $selected = $this->choice(
            'Which models should Botovis access? (comma-separated numbers)',
            $choices,
            null,
            null,
            true, // multiple
        );

        // Map back to class names
        return array_map(function ($choice) use ($models, $choices) {
            $index = array_search($choice, $choices, true);
            return $models[$index];
        }, (array) $selected);
    }

    /**
     * Assign CRUD permissions to each selected model.
     *
     * @param string[] $models
     * @return array<string, string[]> model => permissions
     */
    private function assignPermissions(array $models): array
    {
        $config = [];

        if ($this->option('all') && !$this->option('read-only')) {
            // --all without --read-only: full CRUD for all
            foreach ($models as $model) {
                $config[$model] = self::ACTIONS;
            }
            return $config;
        }

        if ($this->option('read-only')) {
            foreach ($models as $model) {
                $config[$model] = ['read'];
            }
            $this->info('--read-only flag: all models set to read-only.');
            return $config;
        }

        // Interactive: ask per model
        $this->line('');
        $this->info('Assign permissions for each model:');
        $this->line('<fg=gray>Options: create, read, update, delete (comma-separated)</>');
        $this->line('');

        foreach ($models as $model) {
            $baseName = class_basename($model);

            $perms = $this->choice(
                "<fg=cyan>{$baseName}</> — permissions",
                [
                    'Full CRUD (create, read, update, delete)',
                    'Read only (read)',
                    'Read + Write (create, read, update)',
                    'Custom...',
                ],
                0,
            );

            $config[$model] = match ($perms) {
                'Full CRUD (create, read, update, delete)' => ['create', 'read', 'update', 'delete'],
                'Read only (read)' => ['read'],
                'Read + Write (create, read, update)' => ['create', 'read', 'update'],
                'Custom...' => $this->askCustomPermissions($baseName),
                default => ['read'],
            };
        }

        return $config;
    }

    /**
     * Ask for custom permission selection.
     *
     * @return string[]
     */
    private function askCustomPermissions(string $modelName): array
    {
        $selected = $this->choice(
            "{$modelName} — select actions (comma-separated)",
            self::ACTIONS,
            null,
            null,
            true,
        );

        return (array) $selected;
    }

    /**
     * Generate the PHP config snippet.
     */
    private function generateSnippet(array $config): string
    {
        $lines = ["    'models' => ["];

        foreach ($config as $model => $permissions) {
            $permsStr = implode("', '", $permissions);
            $lines[] = "        \\{$model}::class => ['{$permsStr}'],";
        }

        $lines[] = "    ],";

        return implode("\n", $lines);
    }

    /**
     * Output snippet to terminal for copy-paste.
     */
    private function outputSnippet(string $snippet): void
    {
        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📋 Add this to your config/botovis.php:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');
        $this->line($snippet);
        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');
        $this->info('💡 Tip: After updating config, run `php artisan botovis:discover` to verify.');
    }

    /**
     * Write models directly to config/botovis.php.
     */
    private function writeToConfig(array $config, string $snippet): int
    {
        $configPath = config_path('botovis.php');

        if (!file_exists($configPath)) {
            $this->error('config/botovis.php not found. Run `php artisan vendor:publish --tag=botovis-config` first.');
            return self::FAILURE;
        }

        $contents = file_get_contents($configPath);

        // Find and replace the 'models' => [...] block
        $pattern = "/'models'\s*=>\s*\[.*?\]/s";

        if (!preg_match($pattern, $contents)) {
            $this->error("Could not find 'models' array in config/botovis.php.");
            $this->line('');
            $this->line('Add it manually:');
            $this->line('');
            $this->line($snippet);
            return self::FAILURE;
        }

        // Build replacement
        $modelsArray = "    'models' => [\n";
        foreach ($config as $model => $permissions) {
            $permsStr = implode("', '", $permissions);
            $modelsArray .= "        \\{$model}::class => ['{$permsStr}'],\n";
        }
        $modelsArray .= "    ]";

        // We need to match the full 'models' => [...] block including nested content
        $newContents = preg_replace(
            "/'models'\s*=>\s*\[.*?\]/s",
            $this->escapeReplacement($modelsArray),
            $contents,
            1,
        );

        if ($newContents === null || $newContents === $contents) {
            $this->error('Failed to update config file.');
            $this->line('');
            $this->line('Add manually:');
            $this->line('');
            $this->line($snippet);
            return self::FAILURE;
        }

        file_put_contents($configPath, $newContents);

        $this->info('✅ config/botovis.php updated with ' . count($config) . ' model(s)!');
        $this->line('');

        // Show what was written
        $this->line('<fg=gray>Written configuration:</>');
        $this->line('');
        $this->line($snippet);
        $this->line('');
        $this->info('💡 Run `php artisan botovis:discover` to verify the schema.');

        return self::SUCCESS;
    }

    /**
     * Escape special regex replacement characters.
     */
    private function escapeReplacement(string $str): string
    {
        return str_replace(['\\', '$'], ['\\\\', '\\$'], $str);
    }
}
