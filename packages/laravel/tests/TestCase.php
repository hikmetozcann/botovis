<?php

namespace Botovis\Tests;

use Botovis\BotovisServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            BotovisServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Botovis' => \Botovis\Facades\Botovis::class,
        ];
    }
}
