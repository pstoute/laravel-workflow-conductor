<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Exceptions;

use Exception;

class ConditionException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $conditionType = null,
        public readonly ?string $operator = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function notFound(string $type): static
    {
        return new static(
            "Condition type '{$type}' not found.",
            $type
        );
    }

    public static function invalidOperator(string $type, string $operator): static
    {
        return new static(
            "Invalid operator '{$operator}' for condition type '{$type}'.",
            $type,
            $operator
        );
    }

    public static function evaluationFailed(
        string $type,
        string $reason,
        ?\Throwable $previous = null
    ): static {
        return new static(
            "Condition '{$type}' evaluation failed: {$reason}",
            $type,
            null,
            0,
            $previous
        );
    }

    public static function invalidConfiguration(string $type, string $reason): static
    {
        return new static(
            "Invalid configuration for condition '{$type}': {$reason}",
            $type
        );
    }
}
