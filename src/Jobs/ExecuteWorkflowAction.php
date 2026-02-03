<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;
use Pstoute\LaravelWorkflows\Engine\ActionExecutor;
use Pstoute\LaravelWorkflows\Events\ActionExecuted;
use Pstoute\LaravelWorkflows\Events\ActionFailed;
use Pstoute\LaravelWorkflows\Models\WorkflowAction;
use Pstoute\LaravelWorkflows\Models\WorkflowExecution;
use Pstoute\LaravelWorkflows\Models\WorkflowExecutionLog;

class ExecuteWorkflowAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout;

    public function __construct(
        public int $actionId,
        public WorkflowContext $context,
        public int $executionId,
    ) {
        $this->tries = config('workflows.execution.max_retries', 3);
        $this->backoff = config('workflows.execution.retry_delay', 60);
        $this->timeout = config('workflows.execution.timeout', 300);
    }

    /**
     * Execute the job.
     */
    public function handle(ActionExecutor $executor): void
    {
        $action = WorkflowAction::find($this->actionId);
        $execution = WorkflowExecution::find($this->executionId);

        if (! $action) {
            Log::channel(config('workflows.logging.channel', 'stack'))->warning(
                "Action not found: {$this->actionId}"
            );

            return;
        }

        if (! $execution) {
            Log::channel(config('workflows.logging.channel', 'stack'))->warning(
                "Execution not found: {$this->executionId}"
            );

            return;
        }

        $startTime = microtime(true);

        try {
            $result = $executor->execute($action, $this->context);
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

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel(config('workflows.logging.channel', 'stack'))->error(
            "Workflow action job failed: {$exception->getMessage()}",
            [
                'action_id' => $this->actionId,
                'execution_id' => $this->executionId,
                'exception' => $exception,
            ]
        );
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'workflow-action',
            "action:{$this->actionId}",
            "execution:{$this->executionId}",
        ];
    }
}
