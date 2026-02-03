<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Triggers;

use Illuminate\Support\Str;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class WebhookTrigger extends AbstractTrigger
{
    public function getIdentifier(): string
    {
        return 'webhook';
    }

    public function getName(): string
    {
        return 'Webhook';
    }

    public function getDescription(): string
    {
        return 'Triggered by an incoming HTTP webhook request';
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'webhook_id' => [
                    'type' => 'string',
                    'description' => 'Unique webhook identifier (auto-generated if not provided)',
                ],
                'allowed_methods' => [
                    'type' => 'array',
                    'description' => 'Allowed HTTP methods (default: POST)',
                    'items' => ['type' => 'string'],
                    'default' => ['POST'],
                ],
                'validate_payload' => [
                    'type' => 'object',
                    'description' => 'JSON schema for payload validation',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $triggerConfig
     */
    public function shouldTrigger(WorkflowContext $context, array $triggerConfig): bool
    {
        $incomingWebhookId = $context->get('webhook_id');
        $expectedWebhookId = $triggerConfig['webhook_id'] ?? null;

        if ($expectedWebhookId === null) {
            return false;
        }

        return $incomingWebhookId === $expectedWebhookId;
    }

    /**
     * Generate a unique webhook ID.
     */
    public static function generateWebhookId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Get the webhook URL for a given webhook ID.
     */
    public static function getWebhookUrl(string $webhookId): string
    {
        $prefix = config('workflow-conductor.webhooks.route_prefix', 'workflows/webhooks');

        return url("{$prefix}/{$webhookId}");
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableData(): array
    {
        return [
            'webhook_id' => 'The webhook identifier',
            'payload' => 'The webhook request payload',
            'headers' => 'The webhook request headers',
            'method' => 'The HTTP method used',
        ];
    }
}
