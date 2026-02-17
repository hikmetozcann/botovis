<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the Telegram bot integration. When disabled,
    | the webhook endpoint will return 404.
    |
    */
    'enabled' => env('BOTOVIS_TELEGRAM_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Bot Token
    |--------------------------------------------------------------------------
    |
    | Your Telegram Bot API token from @BotFather.
    | Create a bot: https://t.me/BotFather → /newbot
    |
    */
    'bot_token' => env('BOTOVIS_TELEGRAM_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | A secret token to verify incoming webhook requests from Telegram.
    | Set this to a random string. Telegram will send it in the
    | X-Telegram-Bot-Api-Secret-Token header.
    |
    */
    'webhook_secret' => env('BOTOVIS_TELEGRAM_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Connect Code TTL
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) a /connect code remains valid.
    | Users generate a code from their panel and send it to the bot.
    |
    */
    'connect_code_ttl' => 300, // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Guest Message
    |--------------------------------------------------------------------------
    |
    | Message shown to users who haven't linked their Telegram account.
    |
    */
    'guest_message' => env(
        'BOTOVIS_TELEGRAM_GUEST_MESSAGE',
        'Please link your Telegram account first. Go to your app panel and use the "Connect Telegram" option.'
    ),

    /*
    |--------------------------------------------------------------------------
    | Show Steps
    |--------------------------------------------------------------------------
    |
    | When true, the bot will send intermediate reasoning steps
    | (tool calls, observations) as separate messages before the final answer.
    |
    */
    'show_steps' => env('BOTOVIS_TELEGRAM_SHOW_STEPS', false),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Customize the webhook route prefix and middleware.
    | The full webhook URL will be: https://yourapp.com/{prefix}/webhook
    |
    */
    'route' => [
        'prefix' => 'botovis/telegram',
        'middleware' => [],  // No auth middleware — Telegram sends webhooks
    ],

];
