<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Pstoute\LaravelWorkflows\Data\ActionResult;
use Pstoute\LaravelWorkflows\Models\WorkflowAction;
use Pstoute\LaravelWorkflows\Models\WorkflowExecution;

class ActionFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly WorkflowAction $action,
        public readonly WorkflowExecution $execution,
        public readonly ActionResult $result,
    ) {}
}
