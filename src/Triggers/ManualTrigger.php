<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Triggers;

use Pstoute\LaravelWorkflows\Data\WorkflowContext;

class ManualTrigger extends AbstractTrigger
{
    public function getIdentifier(): string
    {
        return 'manual';
    }

    public function getName(): string
    {
        return 'Manual';
    }

    public function getDescription(): string
    {
        return 'Triggered manually via code or API';
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'allowed_users' => [
                    'type' => 'array',
                    'description' => 'User IDs allowed to trigger this workflow (empty = all)',
                    'items' => ['type' => 'integer'],
                ],
                'required_data' => [
                    'type' => 'array',
                    'description' => 'Required data keys that must be present in context',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $triggerConfig
     */
    public function shouldTrigger(WorkflowContext $context, array $triggerConfig): bool
    {
        // Check allowed users
        $allowedUsers = $triggerConfig['allowed_users'] ?? [];

        if (! empty($allowedUsers)) {
            $userId = $context->get('user_id') ?? $context->get('user.id');

            if ($userId === null || ! in_array($userId, $allowedUsers, true)) {
                return false;
            }
        }

        // Check required data
        $requiredData = $triggerConfig['required_data'] ?? [];

        foreach ($requiredData as $key) {
            if (! $context->has($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableData(): array
    {
        return [
            'triggered_by' => 'The user or process that triggered the workflow',
            'triggered_at' => 'The timestamp when the workflow was triggered',
        ];
    }
}
