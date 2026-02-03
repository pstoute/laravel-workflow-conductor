<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pstoute\LaravelWorkflows\Data\ActionResult;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

class DeleteModelAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'delete_model';
    }

    public function getName(): string
    {
        return 'Delete Model';
    }

    public function getDescription(): string
    {
        return 'Delete an Eloquent model instance';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $modelPath = $config['model'] ?? 'model';
        $modelClass = $config['model_class'] ?? null;
        $modelId = $config['model_id'] ?? null;
        $forceDelete = $config['force'] ?? false;

        try {
            // Get model either from context or by class+id
            if ($modelClass && $modelId) {
                if (! class_exists($modelClass)) {
                    return ActionResult::failure("Model class '{$modelClass}' not found");
                }

                // Check allowed models
                $allowedModels = config('workflows.actions.delete_model.allowed_models', ['*']);
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
                $allowedModels = config('workflows.actions.delete_model.allowed_models', ['*']);
                if (! $this->isModelAllowed(get_class($model), $allowedModels)) {
                    return ActionResult::failure('Model is not in the allowed list');
                }
            }

            $modelId = $model->getKey();
            $modelType = get_class($model);
            $softDeleted = false;

            // Handle soft delete
            if ($forceDelete && in_array(SoftDeletes::class, class_uses_recursive($model))) {
                $model->forceDelete();
            } else {
                $model->delete();
                $softDeleted = in_array(SoftDeletes::class, class_uses_recursive($model));
            }

            return ActionResult::success('Model deleted successfully', [
                'deleted_id' => $modelId,
                'deleted_type' => $modelType,
                'soft_deleted' => $softDeleted && ! $forceDelete,
                'force_deleted' => $forceDelete,
            ]);
        } catch (\Throwable $e) {
            return ActionResult::failure('Failed to delete model: ' . $e->getMessage(), $e);
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
                'force' => [
                    'type' => 'boolean',
                    'description' => 'Force delete (skip soft delete)',
                    'default' => false,
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
            'deleted_id' => 'The ID of the deleted model',
            'deleted_type' => 'The class name of the deleted model',
            'soft_deleted' => 'Whether the model was soft deleted',
            'force_deleted' => 'Whether the model was force deleted',
        ];
    }
}
