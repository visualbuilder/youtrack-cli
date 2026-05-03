<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Visualbuilder\YoutrackCli\Http\Controllers\WebhookController;
use Visualbuilder\YoutrackCli\Http\Middleware\VerifyYoutrackWebhook;

Route::post('youtrack/webhook', WebhookController::class)
    ->middleware(VerifyYoutrackWebhook::class)
    ->name('youtrack.webhook');
