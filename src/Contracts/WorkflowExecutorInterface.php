<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Contracts;

use Pstoute\LaravelWorkflows\Data\ExecutionResult;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;
use Pstoute\LaravelWorkflows\Models\Workflow;

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
