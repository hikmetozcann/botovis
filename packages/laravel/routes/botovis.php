<?php

use Illuminate\Support\Facades\Route;
use Botovis\Laravel\Http\BotovisController;

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

Route::post('/chat', [BotovisController::class, 'chat']);
Route::post('/confirm', [BotovisController::class, 'confirm']);
Route::post('/reject', [BotovisController::class, 'reject']);
Route::post('/reset', [BotovisController::class, 'reset']);
Route::get('/schema', [BotovisController::class, 'schema']);
Route::get('/status', [BotovisController::class, 'status']);
