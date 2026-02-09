<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Engine;

use Illuminate\Support\Facades\Log;
use Pstoute\WorkflowConductor\Contracts\WorkflowExecutorInterface;
use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\ExecutionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;
use Pstoute\WorkflowConductor\Events\ActionExecuted;
use Pstoute\WorkflowConductor\Events\ActionFailed;
use Pstoute\WorkflowConductor\Events\WorkflowCompleted;
use Pstoute\WorkflowConductor\Events\WorkflowFailed;
use Pstoute\WorkflowConductor\Events\WorkflowStarted;
use Pstoute\WorkflowConductor\Exceptions\WorkflowException;
use Pstoute\WorkflowConductor\Jobs\ExecuteWorkflow;
use Pstoute\WorkflowConductor\Jobs\ExecuteWorkflowAction;
use Pstoute\WorkflowConductor\Models\Workflow;
use Pstoute\WorkflowConductor\Models\WorkflowAction;
use Pstoute\WorkflowConductor\Models\WorkflowExecution;
use Pstoute\WorkflowConductor\Models\WorkflowExecutionLog;

class WorkflowEngine implements WorkflowExecutorInterface
{
    public function __construct(
        protected ConditionEvaluator $conditionEvaluator,
        protected ActionExecutor $actionExecutor,
        protected TriggerManager $triggerManager,
    ) {}

    /**
     * Execute a workflow synchronously.
     */
    public function execute(Workflow $workflow, WorkflowContext $context): ExecutionResult
    {
        $startTime = microtime(true);

        // Check if workflow is active
        if (! $workflow->isActive()) {
            throw WorkflowException::inactive($workflow);
        }

        // Create execution record
        $execution = $this->createExecution($workflow, $context);

        try {
            // Fire started event
            event(new WorkflowStarted($workflow, $execution, $context));

            // Mark as started
            $execution->markAsStarted();

            // Evaluate conditions
            if (! $this->evaluateConditions($workflow, $context, $execution)) {
                $execution->markAsSkipped('Conditions not met');

                return ExecutionResult::skipped('Workflow conditions not met', $execution);
            }

            // Execute actions
            $actionResults = $this->executeActions($workflow, $context, $execution);

            // Calculate duration
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // Check if any action failed
            $failed = collect($actionResults)->contains(fn (ActionResult $r) => $r->isFailure());

            if ($failed) {
                $failedCount = collect($actionResults)->filter(fn (ActionResult $r) => $r->isFailure())->count();
                $error = "{$failedCount} action(s) failed";

                $execution->markAsFailed($error, ['action_results' => array_map(fn ($r) => $r->toArray(), $actionResults)]);

                $result = ExecutionResult::failure(
                    $error,
                    actionResults: $actionResults,
                    execution: $execution,
                    durationMs: $durationMs,
                );

                event(new WorkflowFailed($workflow, $execution, $result));

                return $result;
            }

            // Mark as completed
            $execution->markAsCompleted(['action_results' => array_map(fn ($r) => $r->toArray(), $actionResults)]);

            $result = ExecutionResult::success(
                actionResults: $actionResults,
                execution: $execution,
                durationMs: $durationMs,
            );

            event(new WorkflowCompleted($workflow, $execution, $result));

            return $result;

        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->logError($workflow, $execution, $e);

            $execution->markAsFailed($e->getMessage());

            $result = ExecutionResult::failure(
                $e->getMessage(),
                $e,
                execution: $execution,
                durationMs: $durationMs,
            );

            event(new WorkflowFailed($workflow, $execution, $result));

            throw WorkflowException::executionFailed($workflow, $e->getMessage(), $execution, $e);
        }
    }

    /**
     * Execute a workflow asynchronously.
     */
    public function executeAsync(Workflow $workflow, WorkflowContext $context): void
    {
        $queue = config('workflow-conductor.execution.queue', 'workflows');
        $connection = config('workflow-conductor.execution.connection');

        $job = new ExecuteWorkflow($workflow->id, $context);

        if ($connection) {
            $job->onConnection($connection);
        }

        dispatch($job->onQueue($queue));
    }

    /**
     * Create an execution record.
     */
    protected function createExecution(Workflow $workflow, WorkflowContext $context): WorkflowExecution
    {
        return WorkflowExecution::create([
            'workflow_id' => $workflow->id,
            'trigger_type' => $context->getMeta('trigger_type', 'manual'),
            'trigger_data' => $context->toArray(),
            'status' => WorkflowExecution::STATUS_PENDING,
        ]);
    }

    /**
     * Evaluate workflow conditions.
     */
    protected function evaluateConditions(
        Workflow $workflow,
        WorkflowContext $context,
        WorkflowExecution $execution
    ): bool {
        $startTime = microtime(true);
        $conditions = $workflow->conditions;

        if ($conditions->isEmpty()) {
            WorkflowExecutionLog::createConditionLog(
                $execution,
                WorkflowExecutionLog::STATUS_SUCCESS,
                ['message' => 'No conditions to evaluate'],
                ['result' => true],
                durationMs: (int) ((microtime(true) - $startTime) * 1000)
            );

            return true;
        }

        try {
            $result = $this->conditionEvaluator->evaluate($conditions, $context);

            WorkflowExecutionLog::createConditionLog(
                $execution,
                $result ? WorkflowExecutionLog::STATUS_SUCCESS : WorkflowExecutionLog::STATUS_SKIPPED,
                ['conditions_count' => $conditions->count()],
                ['result' => $result],
                durationMs: (int) ((microtime(true) - $startTime) * 1000)
            );

            return $result;
        } catch (\Throwable $e) {
            WorkflowExecutionLog::createConditionLog(
                $execution,
                WorkflowExecutionLog::STATUS_FAILED,
                ['conditions_count' => $conditions->count()],
                error: $e->getMessage(),
                durationMs: (int) ((microtime(true) - $startTime) * 1000)
            );

            throw $e;
        }
    }

    /**
     * Execute workflow actions with routing-aware control flow.
     *
     * Supports linear execution, branching (If/Else, GoTo), parallel splits,
     * and event-based waits (Goal). Actions can route to specific nodes via
     * metadata keys: next_node_id, branch_node_ids, wait_for_event.
     *
     * @return array<int, ActionResult>
     */
    protected function executeActions(
        Workflow $workflow,
        WorkflowContext $context,
        WorkflowExecution $execution
    ): array {
        $results = [];
        $actions = $workflow->actions;

        // Build a node_id lookup for routing (only actions that have a node_id)
        $nodeIndex = $actions->filter(fn ($a) => !empty($a->node_id))->keyBy('node_id');

        // Start with the first action by order
        $currentAction = $actions->sortBy('order')->first();

        while ($currentAction) {
            // Handle delayed actions
            if ($currentAction->hasDelay()) {
                $this->scheduleDelayedAction($currentAction, $context, $execution);
                $results[] = ActionResult::success('Action scheduled for delayed execution', [
                    'delayed' => true,
                    'delay_seconds' => $currentAction->delay,
                ]);

                // Move to next sequential action after delay
                $currentAction = $this->getNextSequentialAction($actions, $currentAction);
                continue;
            }

            $result = $this->executeAction($currentAction, $context, $execution);
            $results[] = $result;

            // Stop if action failed and not configured to continue
            if ($result->isFailure() && ! $currentAction->shouldContinueOnFailure()) {
                break;
            }

            // Check for routing metadata from control flow actions
            $nextNodeId = $result->getMetadata('next_node_id');
            if ($nextNodeId) {
                $currentAction = $nodeIndex->get($nextNodeId);
                continue;
            }

            // Check for parallel branches (Split action)
            $branchNodeIds = $result->getMetadata('branch_node_ids');
            if ($branchNodeIds) {
                foreach ($branchNodeIds as $branchNodeId) {
                    $this->dispatchBranch($workflow, $branchNodeId, $context, $execution);
                }
                $currentAction = null; // Parent path ends after split
                continue;
            }

            // Check for event wait (Goal action)
            $waitForEvent = $result->getMetadata('wait_for_event');
            if ($waitForEvent) {
                $this->persistWaitState($execution, $currentAction, $context, $waitForEvent);
                $currentAction = null; // Execution pauses until event
                continue;
            }

            // Default: move to next sequential action
            $currentAction = $this->getNextSequentialAction($actions, $currentAction);
        }

        return $results;
    }

    /**
     * Get the next action in sequential order.
     */
    protected function getNextSequentialAction(
        \Illuminate\Database\Eloquent\Collection $actions,
        WorkflowAction $currentAction
    ): ?WorkflowAction {
        return $actions
            ->where('order', '>', $currentAction->order)
            ->sortBy('order')
            ->first();
    }

    /**
     * Dispatch a parallel branch for execution.
     */
    protected function dispatchBranch(
        Workflow $workflow,
        string $branchNodeId,
        WorkflowContext $context,
        WorkflowExecution $execution
    ): void {
        $queue = config('workflow-conductor.execution.queue', 'workflows');
        $connection = config('workflow-conductor.execution.connection');

        // Find the action by node_id
        $branchAction = $workflow->actions->firstWhere('node_id', $branchNodeId);

        if ($branchAction) {
            $job = new ExecuteWorkflowAction($branchAction->id, $context, $execution->id);

            if ($connection) {
                $job->onConnection($connection);
            }

            dispatch($job->onQueue($queue));
        }
    }

    /**
     * Persist the workflow wait state for a Goal/Event action.
     *
     * @param array<string, mixed> $waitConfig
     */
    protected function persistWaitState(
        WorkflowExecution $execution,
        WorkflowAction $action,
        WorkflowContext $context,
        array $waitConfig
    ): void {
        $execution->update([
            'status' => 'waiting',
            'metadata' => array_merge($execution->metadata ?? [], [
                'waiting_for' => $waitConfig,
                'waiting_action_id' => $action->id,
                'waiting_since' => now()->toIso8601String(),
                'context_snapshot' => $context->toArray(),
            ]),
        ]);
    }

    /**
     * Execute a single action.
     */
    protected function executeAction(
        WorkflowAction $action,
        WorkflowContext $context,
        WorkflowExecution $execution
    ): ActionResult {
        $startTime = microtime(true);

        try {
            $result = $this->actionExecutor->execute($action, $context);
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            WorkflowExecutionLog::createActionLog(
                $execution,
                $action,
                $result->isSuccess() ? WorkflowExecutionLog::STATUS_SUCCESS : WorkflowExecutionLog::STATUS_FAILED,
                $action->configuration,
                $result->output,
                $result->isFailure() ? $result->message : null,
                $durationMs
            );

            if ($result->isSuccess()) {
                event(new ActionExecuted($action, $execution, $result));
            } else {
                event(new ActionFailed($action, $execution, $result));
            }

            return $result;

        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            WorkflowExecutionLog::createActionLog(
                $execution,
                $action,
                WorkflowExecutionLog::STATUS_FAILED,
                $action->configuration,
                error: $e->getMessage(),
                durationMs: $durationMs
            );

            $result = ActionResult::failure($e->getMessage(), $e);

            event(new ActionFailed($action, $execution, $result));

            return $result;
        }
    }

    /**
     * Schedule a delayed action.
     */
    protected function scheduleDelayedAction(
        WorkflowAction $action,
        WorkflowContext $context,
        WorkflowExecution $execution
    ): void {
        $queue = config('workflow-conductor.execution.queue', 'workflows');
        $connection = config('workflow-conductor.execution.connection');

        $job = new ExecuteWorkflowAction($action->id, $context, $execution->id);

        if ($connection) {
            $job->onConnection($connection);
        }

        dispatch($job->onQueue($queue)->delay($action->delay));
    }

    /**
     * Log an error.
     */
    protected function logError(Workflow $workflow, WorkflowExecution $execution, \Throwable $e): void
    {
        if (config('workflow-conductor.logging.enabled', true)) {
            Log::channel(config('workflow-conductor.logging.channel', 'stack'))->error(
                "Workflow execution failed: {$e->getMessage()}",
                [
                    'workflow_id' => $workflow->id,
                    'workflow_name' => $workflow->name,
                    'execution_id' => $execution->id,
                    'exception' => $e,
                ]
            );
        }
    }
}
