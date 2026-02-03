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
use Pstoute\LaravelWorkflows\Engine\WorkflowEngine;
use Pstoute\LaravelWorkflows\Models\Workflow;

class ExecuteWorkflow implements ShouldQueue
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

    /**
     * @param array<string, mixed> $contextData
     */
    public function __construct(
        public int $workflowId,
        public WorkflowContext $context,
    ) {
        $this->tries = config('workflows.execution.max_retries', 3);
        $this->backoff = config('workflows.execution.retry_delay', 60);
        $this->timeout = config('workflows.execution.timeout', 300);
    }

    /**
     * Execute the job.
     */
    public function handle(WorkflowEngine $engine): void
    {
        $workflow = Workflow::find($this->workflowId);

        if (! $workflow) {
            Log::channel(config('workflows.logging.channel', 'stack'))->warning(
                "Workflow not found: {$this->workflowId}"
            );

            return;
        }

        if (! $workflow->isActive()) {
            Log::channel(config('workflows.logging.channel', 'stack'))->info(
                "Skipping inactive workflow: {$workflow->name}"
            );

            return;
        }

        $engine->execute($workflow, $this->context);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel(config('workflows.logging.channel', 'stack'))->error(
            "Workflow job failed: {$exception->getMessage()}",
            [
                'workflow_id' => $this->workflowId,
                'exception' => $exception,
            ]
        );
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'workflow',
            "workflow:{$this->workflowId}",
        ];
    }
}
