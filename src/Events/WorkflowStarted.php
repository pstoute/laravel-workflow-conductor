<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;
use Pstoute\LaravelWorkflows\Models\Workflow;
use Pstoute\LaravelWorkflows\Models\WorkflowExecution;

class WorkflowStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Workflow $workflow,
        public readonly WorkflowExecution $execution,
        public readonly WorkflowContext $context,
    ) {}
}
