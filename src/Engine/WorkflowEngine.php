<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Engine;

use Illuminate\Support\Facades\Log;
use Pstoute\LaravelWorkflows\Contracts\WorkflowExecutorInterface;
use Pstoute\LaravelWorkflows\Data\ActionResult;
use Pstoute\LaravelWorkflows\Data\ExecutionResult;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;
use Pstoute\LaravelWorkflows\Events\ActionExecuted;
use Pstoute\LaravelWorkflows\Events\ActionFailed;
use Pstoute\LaravelWorkflows\Events\WorkflowCompleted;
use Pstoute\LaravelWorkflows\Events\WorkflowFailed;
use Pstoute\LaravelWorkflows\Events\WorkflowStarted;
use Pstoute\LaravelWorkflows\Exceptions\WorkflowException;
use Pstoute\LaravelWorkflows\Jobs\ExecuteWorkflow;
use Pstoute\LaravelWorkflows\Jobs\ExecuteWorkflowAction;
use Pstoute\LaravelWorkflows\Models\Workflow;
use Pstoute\LaravelWorkflows\Models\WorkflowAction;
use Pstoute\LaravelWorkflows\Models\WorkflowExecution;
use Pstoute\LaravelWorkflows\Models\WorkflowExecutionLog;

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
        $queue = config('workflows.execution.queue', 'workflows');
        $connection = config('workflows.execution.connection');

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
     * Execute workflow actions.
     *
     * @return array<int, ActionResult>
     */
    protected function executeActions(
        Workflow $workflow,
        WorkflowContext $context,
        WorkflowExecution $execution
    ): array {
        $results = [];

        foreach ($workflow->actions as $action) {
            // Handle delayed actions
            if ($action->hasDelay()) {
                $this->scheduleDelayedAction($action, $context, $execution);
                $results[] = ActionResult::success('Action scheduled for delayed execution', [
                    'delayed' => true,
                    'delay_seconds' => $action->delay,
                ]);
                continue;
            }

            $result = $this->executeAction($action, $context, $execution);
            $results[] = $result;

            // Stop if action failed and not configured to continue
            if ($result->isFailure() && ! $action->shouldContinueOnFailure()) {
                break;
            }
        }

        return $results;
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
        $queue = config('workflows.execution.queue', 'workflows');
        $connection = config('workflows.execution.connection');

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
        if (config('workflows.logging.enabled', true)) {
            Log::channel(config('workflows.logging.channel', 'stack'))->error(
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
