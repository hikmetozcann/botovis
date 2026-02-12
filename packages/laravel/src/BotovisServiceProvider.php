<?php

declare(strict_types=1);

namespace Botovis\Laravel;

use Illuminate\Support\ServiceProvider;
use Botovis\Core\Contracts\SchemaDiscoveryInterface;
use Botovis\Core\Contracts\LlmDriverInterface;
use Botovis\Laravel\Schema\EloquentSchemaDiscovery;
use Botovis\Laravel\Llm\LlmDriverFactory;
use Botovis\Laravel\Commands\DiscoverCommand;
use Botovis\Laravel\Commands\ChatCommand;

class BotovisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/botovis.php',
            'botovis'
        );

        $this->app->singleton(SchemaDiscoveryInterface::class, function ($app) {
            return new EloquentSchemaDiscovery(
                config('botovis.models', [])
            );
        });

        $this->app->singleton(LlmDriverInterface::class, function ($app) {
            return LlmDriverFactory::make(
                config('botovis.llm', [])
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/botovis.php' => config_path('botovis.php'),
            ], 'botovis-config');

            $this->commands([
                DiscoverCommand::class,
                ChatCommand::class,
            ]);
        }
    }
}
