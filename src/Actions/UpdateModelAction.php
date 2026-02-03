<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Illuminate\Database\Eloquent\Model;
use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class UpdateModelAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'update_model';
    }

    public function getName(): string
    {
        return 'Update Model';
    }

    public function getDescription(): string
    {
        return 'Update an existing Eloquent model instance';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $modelPath = $config['model'] ?? 'model';
        $modelClass = $config['model_class'] ?? null;
        $modelId = $config['model_id'] ?? null;
        $attributes = $config['attributes'] ?? [];

        if (empty($attributes)) {
            return ActionResult::failure('No attributes specified for update');
        }

        try {
            // Get model either from context or by class+id
            if ($modelClass && $modelId) {
                if (! class_exists($modelClass)) {
                    return ActionResult::failure("Model class '{$modelClass}' not found");
                }

                // Check allowed models
                $allowedModels = config('workflow-conductor.actions.update_model.allowed_models', ['*']);
                if (! $this->isModelAllowed($modelClass, $allowedModels)) {
                    return ActionResult::failure("Model '{$modelClass}' is not in the allowed list");
                }

                $model = $modelClass::find($modelId);

                if (! $model) {
                    return ActionResult::failure("Model with ID '{$modelId}' not found");
                }
            } else {
                $model = $context->get($modelPath);

                if (! $model instanceof Model) {
                    return ActionResult::failure("No model found at path '{$modelPath}'");
                }

                // Check allowed models
                $allowedModels = config('workflow-conductor.actions.update_model.allowed_models', ['*']);
                if (! $this->isModelAllowed(get_class($model), $allowedModels)) {
                    return ActionResult::failure('Model is not in the allowed list');
                }
            }

            $model->update($attributes);

            return ActionResult::success('Model updated successfully', [
                'updated_model' => $model->fresh(),
                'updated_id' => $model->getKey(),
                'updated_attributes' => array_keys($attributes),
            ]);
        } catch (\Throwable $e) {
            return ActionResult::failure('Failed to update model: ' . $e->getMessage(), $e);
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
                    'description' => 'Context path to the model (default: "model")',
                    'default' => 'model',
                ],
                'model_class' => [
                    'type' => 'string',
                    'description' => 'Model class name (alternative to context path)',
                ],
                'model_id' => [
                    'type' => 'mixed',
                    'description' => 'Model ID (used with model_class)',
                ],
                'attributes' => [
                    'type' => 'object',
                    'description' => 'Attributes to update',
                    'required' => true,
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
            'updated_model' => 'The updated model instance',
            'updated_id' => 'The ID of the updated model',
            'updated_attributes' => 'List of attribute names that were updated',
        ];
    }
}
