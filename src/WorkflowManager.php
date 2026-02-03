<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Pstoute\WorkflowConductor\Contracts\ActionInterface;
use Pstoute\WorkflowConductor\Contracts\ConditionInterface;
use Pstoute\WorkflowConductor\Contracts\TriggerInterface;
use Pstoute\WorkflowConductor\Data\ExecutionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;
use Pstoute\WorkflowConductor\Engine\ActionExecutor;
use Pstoute\WorkflowConductor\Engine\ConditionEvaluator;
use Pstoute\WorkflowConductor\Engine\TriggerManager;
use Pstoute\WorkflowConductor\Engine\WorkflowEngine;
use Pstoute\WorkflowConductor\Exceptions\WorkflowException;
use Pstoute\WorkflowConductor\Models\Workflow;
use Pstoute\WorkflowConductor\Models\WorkflowExecution;

class WorkflowManager
{
    protected TriggerManager $triggerManager;

    protected ConditionEvaluator $conditionEvaluator;

    protected ActionExecutor $actionExecutor;

    protected WorkflowEngine $engine;

    public function __construct(protected Application $app)
    {
        $this->triggerManager = new TriggerManager();
        $this->conditionEvaluator = new ConditionEvaluator();
        $this->actionExecutor = new ActionExecutor();

        $this->engine = new WorkflowEngine(
            $this->conditionEvaluator,
            $this->actionExecutor,
            $this->triggerManager,
        );
    }

    /**
     * Register a trigger type.
     */
    public function registerTrigger(TriggerInterface $trigger): void
    {
        $this->triggerManager->register($trigger);
    }

    /**
     * Register a condition type.
     */
    public function registerCondition(ConditionInterface $condition): void
    {
        $this->conditionEvaluator->register($condition);
    }

    /**
     * Register an action type.
     */
    public function registerAction(ActionInterface $action): void
    {
        $this->actionExecutor->register($action);
    }

    /**
     * Get a registered trigger by identifier.
     */
    public function getTrigger(string $identifier): ?TriggerInterface
    {
        return $this->triggerManager->get($identifier);
    }

    /**
     * Get a registered condition by identifier.
     */
    public function getCondition(string $identifier): ?ConditionInterface
    {
        return $this->conditionEvaluator->get($identifier);
    }

    /**
     * Get a registered action by identifier.
     */
    public function getAction(string $identifier): ?ActionInterface
    {
        return $this->actionExecutor->get($identifier);
    }

    /**
     * Get all registered triggers.
     *
     * @return array<string, TriggerInterface>
     */
    public function getTriggers(): array
    {
        return $this->triggerManager->all();
    }

    /**
     * Get all registered conditions.
     *
     * @return array<string, ConditionInterface>
     */
    public function getConditions(): array
    {
        return $this->conditionEvaluator->all();
    }

    /**
     * Get all registered actions.
     *
     * @return array<string, ActionInterface>
     */
    public function getActions(): array
    {
        return $this->actionExecutor->all();
    }

    /**
     * Execute a workflow by ID.
     */
    public function execute(int $workflowId, WorkflowContext $context): ExecutionResult
    {
        $workflow = Workflow::find($workflowId);

        if (! $workflow) {
            throw WorkflowException::notFound($workflowId);
        }

        return $this->engine->execute($workflow, $context);
    }

    /**
     * Execute a workflow asynchronously.
     */
    public function executeAsync(int $workflowId, WorkflowContext $context): void
    {
        $workflow = Workflow::find($workflowId);

        if (! $workflow) {
            throw WorkflowException::notFound($workflowId);
        }

        $this->engine->executeAsync($workflow, $context);
    }

    /**
     * Trigger workflows by trigger type.
     */
    public function trigger(string $triggerType, WorkflowContext $context): void
    {
        $context->setMeta('trigger_type', $triggerType);

        $workflows = $this->triggerManager->findMatchingWorkflows($triggerType, $context);

        $mode = config('workflow-conductor.execution.default_mode', 'async');

        foreach ($workflows as $workflow) {
            if ($mode === 'async') {
                $this->engine->executeAsync($workflow, $context);
            } else {
                $this->engine->execute($workflow, $context);
            }
        }
    }

    /**
     * Create a new workflow using the fluent builder.
     */
    public function create(): WorkflowBuilder
    {
        return new WorkflowBuilder($this);
    }

    /**
     * Get the executions query builder.
     *
     * @return Builder<WorkflowExecution>
     */
    public function executions(): Builder
    {
        return WorkflowExecution::query();
    }

    /**
     * Get the workflow engine instance.
     */
    public function getEngine(): WorkflowEngine
    {
        return $this->engine;
    }

    /**
     * Get the trigger manager instance.
     */
    public function getTriggerManager(): TriggerManager
    {
        return $this->triggerManager;
    }

    /**
     * Get the condition evaluator instance.
     */
    public function getConditionEvaluator(): ConditionEvaluator
    {
        return $this->conditionEvaluator;
    }

    /**
     * Get the action executor instance.
     */
    public function getActionExecutor(): ActionExecutor
    {
        return $this->actionExecutor;
    }
}
