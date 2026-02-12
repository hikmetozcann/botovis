<?php

declare(strict_types=1);

namespace Botovis\Laravel\Tests;

use Botovis\Core\Contracts\SchemaDiscoveryInterface;
use Botovis\Laravel\Schema\EloquentSchemaDiscovery;

class ServiceProviderTest extends TestCase
{
    public function test_schema_discovery_is_registered(): void
    {
        $discovery = $this->app->make(SchemaDiscoveryInterface::class);
        $this->assertInstanceOf(EloquentSchemaDiscovery::class, $discovery);
    }

    public function test_config_is_loaded(): void
    {
        $config = config('botovis');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('models', $config);
        $this->assertArrayHasKey('llm', $config);
        $this->assertArrayHasKey('security', $config);
    }

    public function test_discover_command_is_registered(): void
    {
        $this->artisan('botovis:discover')
            ->assertSuccessful();
    }

    public function test_empty_models_shows_warning(): void
    {
        $this->artisan('botovis:discover')
            ->expectsOutput('No models configured for Botovis.')
            ->assertSuccessful();
    }
}
