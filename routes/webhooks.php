<?php

use Illuminate\Support\Facades\Route;
use Pstoute\LaravelWorkflows\Http\Controllers\WebhookTriggerController;
use Pstoute\LaravelWorkflows\Http\Middleware\ValidateWebhookSignature;

$prefix = config('workflows.webhooks.route_prefix', 'workflows/webhooks');
$middleware = config('workflows.webhooks.middleware', ['api']);

Route::prefix($prefix)
    ->middleware(array_merge($middleware, [ValidateWebhookSignature::class]))
    ->group(function () {
        Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '{webhookId}', [WebhookTriggerController::class, 'handle'])
            ->name('workflows.webhook');
    });
