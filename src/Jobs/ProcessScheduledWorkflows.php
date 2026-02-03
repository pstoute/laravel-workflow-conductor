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
use Pstoute\LaravelWorkflows\Engine\TriggerManager;
use Pstoute\LaravelWorkflows\Engine\WorkflowEngine;
use Pstoute\LaravelWorkflows\Models\Workflow;

class ProcessScheduledWorkflows implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(WorkflowEngine $engine, TriggerManager $triggerManager): void
    {
        // Find all active workflows with scheduled triggers
        $workflows = Workflow::query()
            ->active()
            ->withTriggerType('scheduled')
            ->with('triggers')
            ->get();

        $now = now();

        foreach ($workflows as $workflow) {
            foreach ($workflow->triggers as $trigger) {
                if ($trigger->type !== 'scheduled') {
                    continue;
                }

                $context = new WorkflowContext([
                    'scheduled_at' => $now,
                    'cron_expression' => $trigger->getConfig('cron'),
                ], [
                    'trigger_type' => 'scheduled',
                ]);

                // Check if trigger should fire
                if ($triggerManager->shouldTrigger($trigger, $context)) {
                    try {
                        // Execute asynchronously to avoid blocking
                        $engine->executeAsync($workflow, $context);

                        Log::channel(config('workflows.logging.channel', 'stack'))->info(
                            "Scheduled workflow triggered: {$workflow->name}",
                            ['workflow_id' => $workflow->id]
                        );
                    } catch (\Throwable $e) {
                        Log::channel(config('workflows.logging.channel', 'stack'))->error(
                            "Failed to trigger scheduled workflow: {$e->getMessage()}",
                            [
                                'workflow_id' => $workflow->id,
                                'exception' => $e,
                            ]
                        );
                    }
                }
            }
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['scheduled-workflows'];
    }
}
