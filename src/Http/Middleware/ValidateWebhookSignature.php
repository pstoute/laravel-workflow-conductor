<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('workflows.webhooks.signing_secret');

        // If no secret is configured, skip validation
        if (empty($secret)) {
            return $next($request);
        }

        $signature = $request->header('X-Workflow-Signature');

        if (empty($signature)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing signature header',
            ], 401);
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        // Support both raw signature and prefixed signature (sha256=...)
        $providedSignature = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        if (! hash_equals($expectedSignature, $providedSignature)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature',
            ], 401);
        }

        return $next($request);
    }

    /**
     * Generate a signature for a payload.
     */
    public static function sign(string $payload, ?string $secret = null): string
    {
        $secret = $secret ?? config('workflows.webhooks.signing_secret');

        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }
}
