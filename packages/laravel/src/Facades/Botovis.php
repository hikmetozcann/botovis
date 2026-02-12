<?php

namespace Botovis\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string version()
 * @method static mixed getConfig(string $key, mixed $default = null)
 *
 * @see \Botovis\Botovis
 */
class Botovis extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \Botovis\Botovis::class;
    }
}
