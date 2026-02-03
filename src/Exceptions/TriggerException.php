<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Exceptions;

use Exception;

class TriggerException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $triggerType = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function notFound(string $type): static
    {
        return new static(
            "Trigger type '{$type}' not found.",
            $type
        );
    }

    public static function invalidConfiguration(string $type, string $reason): static
    {
        return new static(
            "Invalid configuration for trigger '{$type}': {$reason}",
            $type
        );
    }

    public static function evaluationFailed(string $type, string $reason, ?\Throwable $previous = null): static
    {
        return new static(
            "Trigger '{$type}' evaluation failed: {$reason}",
            $type,
            0,
            $previous
        );
    }
}
