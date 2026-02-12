<?php

declare(strict_types=1);

namespace Botovis\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Botovis\Core\Contracts\SchemaDiscoveryInterface;
use Botovis\Core\Contracts\LlmDriverInterface;
use Botovis\Core\Contracts\ActionExecutorInterface;
use Botovis\Core\Contracts\ConversationManagerInterface;
use Botovis\Core\Intent\IntentResolver;
use Botovis\Core\Orchestrator;
use Botovis\Laravel\Schema\EloquentSchemaDiscovery;
use Botovis\Laravel\Llm\LlmDriverFactory;
use Botovis\Laravel\Action\EloquentActionExecutor;
use Botovis\Laravel\Conversation\CacheConversationManager;
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

        // ── Core bindings ──

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

        $this->app->singleton(ActionExecutorInterface::class, function ($app) {
            $schema = $app->make(SchemaDiscoveryInterface::class)->discover();
            return new EloquentActionExecutor($schema);
        });

        // ── Conversation persistence ──

        $this->app->singleton(ConversationManagerInterface::class, function ($app) {
            return new CacheConversationManager();
        });

        // ── Orchestrator (used by both CLI and HTTP) ──

        $this->app->singleton(Orchestrator::class, function ($app) {
            $schema = $app->make(SchemaDiscoveryInterface::class)->discover();
            $llm    = $app->make(LlmDriverInterface::class);

            return new Orchestrator(
                new IntentResolver($llm, $schema),
                $app->make(ActionExecutorInterface::class),
                $app->make(ConversationManagerInterface::class),
            );
        });
    }

    public function boot(): void
    {
        // ── Config publishing ──
        $this->publishes([
            __DIR__ . '/../config/botovis.php' => config_path('botovis.php'),
        ], 'botovis-config');

        // ── Widget asset publishing ──
        $widgetDist = realpath(__DIR__ . '/../../widget/dist');
        if ($widgetDist) {
            $this->publishes([
                $widgetDist => public_path('vendor/botovis'),
            ], 'botovis-assets');
        }

        // ── Views ──
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'botovis');
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/botovis'),
        ], 'botovis-views');

        // ── Blade directive: @botovisWidget ──
        Blade::directive('botovisWidget', function ($expression) {
            $defaults = "['endpoint' => '/' . config('botovis.route.prefix', 'botovis'), 'lang' => 'tr', 'theme' => 'auto', 'position' => 'bottom-right']";
            $merged = empty($expression) ? $defaults : "array_merge({$defaults}, {$expression})";
            return "<?php echo view('botovis::widget', {$merged})->render(); ?>";
        });

        // ── Artisan commands ──
        if ($this->app->runningInConsole()) {
            $this->commands([
                DiscoverCommand::class,
                ChatCommand::class,
            ]);
        }

        // ── HTTP routes ──
        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        $prefix     = config('botovis.route.prefix', 'botovis');
        $middleware  = config('botovis.route.middleware', ['web', 'auth']);

        Route::prefix($prefix)
            ->middleware($middleware)
            ->group(__DIR__ . '/../routes/botovis.php');
    }
}
