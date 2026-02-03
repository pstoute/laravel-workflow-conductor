<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Triggers;

use Pstoute\WorkflowConductor\Data\WorkflowContext;

class ModelDeletedTrigger extends AbstractTrigger
{
    public function getIdentifier(): string
    {
        return 'model.deleted';
    }

    public function getName(): string
    {
        return 'Model Deleted';
    }

    public function getDescription(): string
    {
        return 'Triggered when a model instance is deleted';
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

        return is_a($model, $expectedModel, true);
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableData(): array
    {
        return [
            'model' => 'The deleted model instance',
        ];
    }
}
