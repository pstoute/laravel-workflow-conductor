<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Triggers;

use Pstoute\LaravelWorkflows\Data\WorkflowContext;

class ModelCreatedTrigger extends AbstractTrigger
{
    public function getIdentifier(): string
    {
        return 'model.created';
    }

    public function getName(): string
    {
        return 'Model Created';
    }

    public function getDescription(): string
    {
        return 'Triggered when a new model instance is created';
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'model' => [
                    'type' => 'string',
                    'description' => 'The fully qualified model class name',
                    'required' => true,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $triggerConfig
     */
    public function shouldTrigger(WorkflowContext $context, array $triggerConfig): bool
    {
        $model = $context->get('model');
        $expectedModel = $triggerConfig['model'] ?? null;

        if ($model === null || $expectedModel === null) {
            return false;
        }

        // Check if the model class matches
        return is_a($model, $expectedModel, true);
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableData(): array
    {
        return [
            'model' => 'The created model instance',
        ];
    }
}
