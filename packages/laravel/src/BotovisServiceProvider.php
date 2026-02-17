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
use Botovis\Core\Contracts\ConversationRepositoryInterface;
use Botovis\Core\Intent\IntentResolver;
use Botovis\Core\Orchestrator;
use Botovis\Core\Agent\AgentOrchestrator;
use Botovis\Core\Tools\ToolRegistry;
use Botovis\Laravel\Schema\EloquentSchemaDiscovery;
use Botovis\Laravel\Llm\LlmDriverFactory;
use Botovis\Laravel\Action\EloquentActionExecutor;
use Botovis\Laravel\Conversation\CacheConversationManager;
use Botovis\Laravel\Repositories\EloquentConversationRepository;
use Botovis\Laravel\Repositories\SessionConversationRepository;
use Botovis\Laravel\Security\BotovisAuthorizer;
use Botovis\Laravel\Commands\DiscoverCommand;
use Botovis\Laravel\Commands\ChatCommand;
use Botovis\Laravel\Commands\ModelsCommand;
use Botovis\Laravel\Tools\SearchRecordsTool;
use Botovis\Laravel\Tools\CountRecordsTool;
use Botovis\Laravel\Tools\GetSampleDataTool;
use Botovis\Laravel\Tools\GetColumnStatsTool;
use Botovis\Laravel\Tools\AggregateTool;
use Botovis\Laravel\Tools\CreateRecordTool;
use Botovis\Laravel\Tools\UpdateRecordTool;
use Botovis\Laravel\Tools\DeleteRecordTool;

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

        // ── Security / Authorization ──

        $this->app->singleton(BotovisAuthorizer::class, function ($app) {
            return new BotovisAuthorizer();
        });

        // ── Conversation Repository ──

        $this->app->singleton(ConversationRepositoryInterface::class, function ($app) {
            $enabled = config('botovis.conversations.enabled', true);
            if (!$enabled) {
                return null;
            }

            $driver = config('botovis.conversations.driver', 'database');

            return match ($driver) {
                'session' => new SessionConversationRepository(),
                default => new EloquentConversationRepository(),
            };
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

        // ── Tool Registry (for Agent mode) ──

        $this->app->singleton(ToolRegistry::class, function ($app) {
            $schema = $app->make(SchemaDiscoveryInterface::class)->discover();

            $registry = new ToolRegistry();
            $registry->registerMany([
                new SearchRecordsTool($schema),
                new CountRecordsTool($schema),
                new GetSampleDataTool($schema),
                new GetColumnStatsTool($schema),
                new AggregateTool($schema),
                new CreateRecordTool($schema),
                new UpdateRecordTool($schema),
                new DeleteRecordTool($schema),
            ]);

            return $registry;
        });

        // ── Agent Orchestrator (new ReAct-based system) ──

        $this->app->singleton(AgentOrchestrator::class, function ($app) {
            $schema = $app->make(SchemaDiscoveryInterface::class)->discover();

            return new AgentOrchestrator(
                $app->make(LlmDriverInterface::class),
                $app->make(ToolRegistry::class),
                $schema,
                $app->make(ConversationManagerInterface::class),
                config('botovis.locale', 'en'),
            );
        });
    }

    public function boot(): void
    {
        // ── Config publishing ──
        $this->publishes([
            __DIR__ . '/../config/botovis.php' => config_path('botovis.php'),
        ], 'botovis-config');

        // ── Migration publishing ──
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'botovis-migrations');

            // Also load migrations directly (so users don't have to publish)
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        // ── Widget asset publishing ──
        $this->publishes([
            __DIR__ . '/../resources/dist' => public_path('vendor/botovis'),
        ], 'botovis-assets');

        // ── Views ──
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'botovis');
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/botovis'),
        ], 'botovis-views');

        // ── Blade directive: @botovisWidget ──
        Blade::directive('botovisWidget', function ($expression) {
            $defaults = "['endpoint' => '/' . config('botovis.route.prefix', 'botovis'), 'lang' => config('botovis.locale', 'en'), 'theme' => 'auto', 'position' => 'bottom-right', 'streaming' => config('botovis.agent.streaming', true)]";
            $merged = empty($expression) ? $defaults : "array_merge({$defaults}, {$expression})";
            return "<?php echo view('botovis::widget', {$merged})->render(); ?>";
        });

        // ── Artisan commands ──
        if ($this->app->runningInConsole()) {
            $this->commands([
                DiscoverCommand::class,
                ChatCommand::class,
                ModelsCommand::class,
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
