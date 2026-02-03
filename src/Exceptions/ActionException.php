<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Exceptions;

use Exception;
use Pstoute\WorkflowConductor\Models\WorkflowAction;

class ActionException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $actionType = null,
        public readonly ?WorkflowAction $action = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function notFound(string $type): static
    {
        return new static(
            "Action type '{$type}' not found.",
            $type
        );
    }

    public static function invalidConfiguration(string $type, string $reason): static
    {
        return new static(
            "Invalid configuration for action '{$type}': {$reason}",
            $type
        );
    }

    public static function executionFailed(
        string $type,
        string $reason,
        ?WorkflowAction $action = null,
        ?\Throwable $previous = null
    ): static {
        return new static(
            "Action '{$type}' execution failed: {$reason}",
            $type,
            $action,
            0,
            $previous
        );
    }

    public static function timeout(string $type, int $timeout, ?WorkflowAction $action = null): static
    {
        return new static(
            "Action '{$type}' timed out after {$timeout} seconds.",
            $type,
            $action
        );
    }
}
