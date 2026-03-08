<?php

use App\Http\Controllers\WebhookController;
use App\Http\Middleware\ValidateWebhookSecret;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhook', WebhookController::class)
    ->middleware(ValidateWebhookSecret::class)
    ->name('telegram.webhook');
