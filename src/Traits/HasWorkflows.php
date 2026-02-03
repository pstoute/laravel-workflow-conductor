<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Traits;

use Pstoute\WorkflowConductor\Data\WorkflowContext;
use Pstoute\WorkflowConductor\Facades\Conductor;

/**
 * Add this trait to Eloquent models to automatically trigger workflows on model events.
 *
 * Usage:
 * ```php
 * class User extends Model
 * {
 *     use HasWorkflows;
 * }
 * ```
 *
 * This will automatically trigger workflows on created, updated, and deleted events.
 * You can also manually trigger workflows using:
 * ```php
 * $user->triggerWorkflows('created');
 * ```
 */
trait HasWorkflows
{
    /**
     * Boot the trait.
     */
    public static function bootHasWorkflows(): void
    {
        static::created(function ($model) {
            $model->triggerWorkflows('created');
        });

        static::updated(function ($model) {
            $model->triggerWorkflows('updated');
        });

        static::deleted(function ($model) {
            $model->triggerWorkflows('deleted');
        });
    }

    /**
     * Trigger workflows for a specific event.
     */
    public function triggerWorkflows(string $event): void
    {
        $triggerType = "model.{$event}";

        $data = [
            'model' => $this,
        ];

        // Add changes for updated event
        if ($event === 'updated') {
            $data['changes'] = $this->getChanges();
            $data['original'] = $this->getOriginal();
        }

        $context = new WorkflowContext($data, [
            'trigger_type' => $triggerType,
            'model_class' => static::class,
            'model_id' => $this->getKey(),
        ]);

        Conductor::trigger($triggerType, $context);
    }

    /**
     * Manually trigger a specific workflow for this model.
     *
     * @param array<string, mixed> $additionalData
     */
    public function triggerWorkflow(int $workflowId, array $additionalData = []): void
    {
        $context = new WorkflowContext(array_merge([
            'model' => $this,
        ], $additionalData), [
            'trigger_type' => 'manual',
            'model_class' => static::class,
            'model_id' => $this->getKey(),
        ]);

        Conductor::executeAsync($workflowId, $context);
    }

    /**
     * Get all workflow executions for this model.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Pstoute\WorkflowConductor\Models\WorkflowExecution>
     */
    public function getWorkflowExecutions()
    {
        return Conductor::executions()
            ->whereJsonContains('trigger_data->metadata->model_class', static::class)
            ->whereJsonContains('trigger_data->metadata->model_id', $this->getKey())
            ->get();
    }
}
