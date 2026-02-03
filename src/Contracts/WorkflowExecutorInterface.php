<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Contracts;

use Pstoute\WorkflowConductor\Data\ExecutionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;
use Pstoute\WorkflowConductor\Models\Workflow;

interface WorkflowExecutorInterface
{
    /**
     * Execute a workflow with the given context.
     */
    public function execute(Workflow $workflow, WorkflowContext $context): ExecutionResult;

    /**
     * Execute a workflow asynchronously.
     */
    public function executeAsync(Workflow $workflow, WorkflowContext $context): void;
}
