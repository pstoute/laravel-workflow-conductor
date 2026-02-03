<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Pstoute\WorkflowConductor\Data\ExecutionResult;
use Pstoute\WorkflowConductor\Models\Workflow;
use Pstoute\WorkflowConductor\Models\WorkflowExecution;

class WorkflowFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Workflow $workflow,
        public readonly WorkflowExecution $execution,
        public readonly ExecutionResult $result,
    ) {}
}
