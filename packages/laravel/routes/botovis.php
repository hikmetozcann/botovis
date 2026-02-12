<?php

use Illuminate\Support\Facades\Route;
use Botovis\Laravel\Http\BotovisController;
use Botovis\Laravel\Http\ConversationController;

/*
|--------------------------------------------------------------------------
| Botovis API Routes
|--------------------------------------------------------------------------
|
| These routes are registered by BotovisServiceProvider with the prefix
| and middleware defined in config/botovis.php → route.
|
| Default: /botovis/* with ['web', 'auth'] middleware
|
*/

// Chat endpoints
Route::post('/chat', [BotovisController::class, 'chat']);
Route::post('/confirm', [BotovisController::class, 'confirm']);
Route::post('/reject', [BotovisController::class, 'reject']);
Route::post('/reset', [BotovisController::class, 'reset']);
Route::get('/schema', [BotovisController::class, 'schema']);
Route::get('/status', [BotovisController::class, 'status']);

// Streaming endpoints (SSE)
Route::post('/stream', [BotovisController::class, 'stream']);
Route::post('/stream-confirm', [BotovisController::class, 'streamConfirm']);

// Conversation management
Route::get('/conversations', [ConversationController::class, 'index']);
Route::post('/conversations', [ConversationController::class, 'store']);
Route::get('/conversations/{id}', [ConversationController::class, 'show']);
Route::delete('/conversations/{id}', [ConversationController::class, 'destroy']);
Route::patch('/conversations/{id}/title', [ConversationController::class, 'updateTitle']);
