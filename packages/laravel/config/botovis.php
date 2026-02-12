<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Botovis API Key
    |--------------------------------------------------------------------------
    |
    | Your Botovis API key for authentication.
    |
    */
    'api_key' => env('BOTOVIS_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | The environment Botovis is running in.
    | Supported: "production", "staging", "development"
    |
    */
    'env' => env('BOTOVIS_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, Botovis will output detailed debug information.
    |
    */
    'debug' => env('BOTOVIS_DEBUG', false),

];
