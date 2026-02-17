<?php

declare(strict_types=1);

namespace Botovis\Telegram;

use Botovis\Core\Agent\AgentOrchestrator;
use Botovis\Telegram\Commands\SetupCommand;
use Botovis\Telegram\Http\WebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TelegramServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/botovis-telegram.php',
            'botovis-telegram'
        );

        // ── Telegram API client ──
        $this->app->singleton(TelegramApi::class, function ($app) {
            $token = config('botovis-telegram.bot_token', '');
            return new TelegramApi($token);
        });

        // ── Telegram Adapter ──
        $this->app->singleton(TelegramAdapter::class, function ($app) {
            return new TelegramAdapter(
                $app->make(TelegramApi::class),
                $app->make(AgentOrchestrator::class),
            );
        });
    }

    public function boot(): void
    {
        // ── Config publishing ──
        $this->publishes([
            __DIR__ . '/../config/botovis-telegram.php' => config_path('botovis-telegram.php'),
        ], 'botovis-telegram-config');

        // ── Migration publishing ──
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'botovis-telegram-migrations');

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        // ── Artisan commands ──
        if ($this->app->runningInConsole()) {
            $this->commands([
                SetupCommand::class,
            ]);
        }

        // ── Routes ──
        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        $prefix = config('botovis-telegram.route.prefix', 'botovis/telegram');
        $middleware = config('botovis-telegram.route.middleware', []);

        // Webhook route (no auth — protected by secret token)
        Route::prefix($prefix)
            ->middleware($middleware)
            ->group(__DIR__ . '/../routes/telegram.php');

        // Panel API routes (requires auth — same middleware as main Botovis)
        $botovisPrefix = config('botovis.route.prefix', 'botovis');
        $botovisMiddleware = config('botovis.route.middleware', ['web', 'auth']);

        Route::prefix($botovisPrefix . '/telegram')
            ->middleware($botovisMiddleware)
            ->group(function () {
                Route::post('/connect-code', [WebhookController::class, 'generateCode'])
                    ->name('botovis.telegram.connect-code');
                Route::post('/disconnect', [WebhookController::class, 'disconnect'])
                    ->name('botovis.telegram.disconnect');
            });
    }
}
