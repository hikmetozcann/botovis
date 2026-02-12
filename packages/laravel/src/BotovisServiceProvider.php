<?php

namespace Botovis;

use Illuminate\Support\ServiceProvider;

class BotovisServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/botovis.php',
            'botovis'
        );

        $this->app->singleton(Botovis::class, function ($app) {
            return new Botovis(
                config('botovis')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/botovis.php' => config_path('botovis.php'),
            ], 'botovis-config');
        }
    }
}
