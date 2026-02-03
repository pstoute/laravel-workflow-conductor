<?php

use Illuminate\Support\Facades\Route;
use Pstoute\WorkflowConductor\Http\Controllers\WebhookTriggerController;
use Pstoute\WorkflowConductor\Http\Middleware\ValidateWebhookSignature;

$prefix = config('workflow-conductor.webhooks.route_prefix', 'workflows/webhooks');
$middleware = config('workflow-conductor.webhooks.middleware', ['api']);

Route::prefix($prefix)
    ->middleware(array_merge($middleware, [ValidateWebhookSignature::class]))
    ->group(function () {
        Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '{webhookId}', [WebhookTriggerController::class, 'handle'])
            ->name('workflows.webhook');
    });
