<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Botovis Models (Whitelist)
    |--------------------------------------------------------------------------
    |
    | Define which models Botovis can access and what actions are allowed.
    | Models not listed here are completely invisible to Botovis.
    |
    | Format:
    |   ModelClass::class => ['create', 'read', 'update', 'delete']
    |
    | Example:
    |   App\Models\Product::class => ['create', 'read', 'update'],
    |   App\Models\Category::class => ['read'],
    |
    */
    'models' => [
        // App\Models\Product::class => ['create', 'read', 'update', 'delete'],
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM Driver
    |--------------------------------------------------------------------------
    |
    | The AI driver to use for natural language understanding.
    | Supported: "openai", "anthropic", "ollama"
    |
    */
    'llm' => [
        'driver' => env('BOTOVIS_LLM_DRIVER', 'openai'),

        'openai' => [
            'api_key' => env('BOTOVIS_OPENAI_API_KEY'),
            'model' => env('BOTOVIS_OPENAI_MODEL', 'gpt-4o'),
            'base_url' => env('BOTOVIS_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],

        'anthropic' => [
            'api_key' => env('BOTOVIS_ANTHROPIC_API_KEY'),
            'model' => env('BOTOVIS_ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        ],

        'ollama' => [
            'model' => env('BOTOVIS_OLLAMA_MODEL', 'llama3'),
            'base_url' => env('BOTOVIS_OLLAMA_BASE_URL', 'http://localhost:11434'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | respect_policies: When true, Botovis checks Laravel Gates/Policies
    |                   before executing any action.
    |
    | require_confirmation: Actions that need user confirmation before executing.
    |                       'read' actions never require confirmation.
    |
    */
    'security' => [
        'respect_policies' => true,
        'require_confirmation' => ['create', 'update', 'delete'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */
    'route' => [
        'prefix' => 'botovis',
        'middleware' => ['web', 'auth'],
    ],

];
