<?php

namespace Botovis;

class Botovis
{
    /**
     * The Botovis configuration.
     */
    protected array $config;

    /**
     * Create a new Botovis instance.
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaults(), $config);
    }

    /**
     * Get the default configuration.
     */
    protected function defaults(): array
    {
        return [
            'api_key' => env('BOTOVIS_API_KEY'),
            'env' => env('BOTOVIS_ENV', 'production'),
            'debug' => env('BOTOVIS_DEBUG', false),
        ];
    }

    /**
     * Get a configuration value.
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get the current Botovis SDK version.
     */
    public function version(): string
    {
        return '0.1.0';
    }
}
