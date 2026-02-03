<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Triggers;

use Illuminate\Database\Eloquent\Model;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

class ModelUpdatedTrigger extends AbstractTrigger
{
    public function getIdentifier(): string
    {
        return 'model.updated';
    }

    public function getName(): string
    {
        return 'Model Updated';
    }

    public function getDescription(): string
    {
        return 'Triggered when a model instance is updated';
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
                'watch_fields' => [
                    'type' => 'array',
                    'description' => 'Only trigger when these specific fields change (optional)',
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
        $model = $context->get('model');
        $expectedModel = $triggerConfig['model'] ?? null;

        if ($model === null || $expectedModel === null) {
            return false;
        }

        // Check if the model class matches
        if (! is_a($model, $expectedModel, true)) {
            return false;
        }

        // Check if specific fields should be watched
        $watchFields = $triggerConfig['watch_fields'] ?? [];

        if (empty($watchFields)) {
            return true;
        }

        // Check if any watched field was changed
        if ($model instanceof Model) {
            $changedFields = array_keys($model->getChanges());

            return ! empty(array_intersect($watchFields, $changedFields));
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableData(): array
    {
        return [
            'model' => 'The updated model instance',
            'changes' => 'Array of changed attributes',
            'original' => 'Array of original values',
        ];
    }
}
