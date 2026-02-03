<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;
use Pstoute\LaravelWorkflows\Facades\Workflows;

class WebhookTriggerController extends Controller
{
    /**
     * Handle incoming webhook requests.
     */
    public function handle(Request $request, string $webhookId): JsonResponse
    {
        $context = new WorkflowContext([
            'webhook_id' => $webhookId,
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], [
            'trigger_type' => 'webhook',
        ]);

        try {
            Workflows::trigger('webhook', $context);

            return response()->json([
                'success' => true,
                'message' => 'Webhook received',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process webhook',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 500);
        }
    }
}
