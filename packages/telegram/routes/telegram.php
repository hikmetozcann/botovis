<?php

use Botovis\Telegram\Http\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Botovis Telegram Routes
|--------------------------------------------------------------------------
|
| Webhook endpoint (no auth — Telegram sends updates here).
| Protected by secret token validation in the controller.
|
*/

Route::post('/webhook', [WebhookController::class, 'handle'])
    ->name('botovis.telegram.webhook');
