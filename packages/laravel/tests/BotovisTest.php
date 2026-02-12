<?php

namespace Botovis\Tests;

use Botovis\Botovis;

class BotovisTest extends TestCase
{
    public function test_can_resolve_botovis_from_container(): void
    {
        $botovis = app(Botovis::class);

        $this->assertInstanceOf(Botovis::class, $botovis);
    }

    public function test_returns_version(): void
    {
        $botovis = app(Botovis::class);

        $this->assertEquals('0.1.0', $botovis->version());
    }

    public function test_can_get_config_value(): void
    {
        $botovis = new Botovis(['api_key' => 'test-key']);

        $this->assertEquals('test-key', $botovis->getConfig('api_key'));
    }

    public function test_returns_default_for_missing_config(): void
    {
        $botovis = new Botovis();

        $this->assertEquals('fallback', $botovis->getConfig('nonexistent', 'fallback'));
    }

    public function test_facade_returns_version(): void
    {
        $version = \Botovis\Facades\Botovis::version();

        $this->assertEquals('0.1.0', $version);
    }
}
