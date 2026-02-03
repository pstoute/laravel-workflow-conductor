<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Exceptions;

use Exception;
use Pstoute\LaravelWorkflows\Models\Workflow;
use Pstoute\LaravelWorkflows\Models\WorkflowExecution;

class WorkflowException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?Workflow $workflow = null,
        public readonly ?WorkflowExecution $execution = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function notFound(int $id): static
    {
        return new static("Workflow with ID {$id} not found.");
    }

    public static function inactive(Workflow $workflow): static
    {
        return new static(
            "Workflow '{$workflow->name}' is inactive.",
            $workflow
        );
    }

    public static function executionFailed(
        Workflow $workflow,
        string $reason,
        ?WorkflowExecution $execution = null,
        ?\Throwable $previous = null
    ): static {
        return new static(
            "Workflow '{$workflow->name}' execution failed: {$reason}",
            $workflow,
            $execution,
            0,
            $previous
        );
    }

    public static function rateLimited(Workflow $workflow): static
    {
        return new static(
            "Workflow '{$workflow->name}' execution rate limited.",
            $workflow
        );
    }
}
