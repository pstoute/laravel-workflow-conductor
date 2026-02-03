<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class CreateModelAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'create_model';
    }

    public function getName(): string
    {
        return 'Create Model';
    }

    public function getDescription(): string
    {
        return 'Create a new Eloquent model instance';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $modelClass = $config['model'] ?? null;
        $attributes = $config['attributes'] ?? [];
        $contextKey = $config['context_key'] ?? 'created_model';

        if (empty($modelClass)) {
            return ActionResult::failure('No model class specified');
        }

        if (! class_exists($modelClass)) {
            return ActionResult::failure("Model class '{$modelClass}' not found");
        }

        // Check allowed models
        $allowedModels = config('workflow-conductor.actions.create_model.allowed_models', ['*']);
        if (! $this->isModelAllowed($modelClass, $allowedModels)) {
            return ActionResult::failure("Model '{$modelClass}' is not in the allowed list");
        }

        try {
            $model = new $modelClass();

            if (! method_exists($model, 'create')) {
                return ActionResult::failure("Model '{$modelClass}' does not support create");
            }

            $created = $modelClass::create($attributes);

            return ActionResult::success('Model created successfully', [
                $contextKey => $created,
                'created_id' => $created->getKey(),
                'created_type' => $modelClass,
            ]);
        } catch (\Throwable $e) {
            return ActionResult::failure('Failed to create model: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Check if the model is in the allowed list.
     *
     * @param array<string> $allowedModels
     */
    protected function isModelAllowed(string $modelClass, array $allowedModels): bool
    {
        if (in_array('*', $allowedModels)) {
            return true;
        }

        return in_array($modelClass, $allowedModels);
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
                    'description' => 'Fully qualified model class name',
                    'required' => true,
                ],
                'attributes' => [
                    'type' => 'object',
                    'description' => 'Model attributes to set',
                    'required' => true,
                ],
                'context_key' => [
                    'type' => 'string',
                    'description' => 'Key to store created model in context',
                    'default' => 'created_model',
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getOutputData(): array
    {
        return [
            'created_model' => 'The created model instance (or custom key)',
            'created_id' => 'The ID of the created model',
            'created_type' => 'The class name of the created model',
        ];
    }
}
