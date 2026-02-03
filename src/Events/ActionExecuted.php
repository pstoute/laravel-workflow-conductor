<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Models\WorkflowAction;
use Pstoute\WorkflowConductor\Models\WorkflowExecution;

class ActionExecuted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly WorkflowAction $action,
        public readonly WorkflowExecution $execution,
        public readonly ActionResult $result,
    ) {}
}
