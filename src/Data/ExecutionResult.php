<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Data;

use Illuminate\Contracts\Support\Arrayable;
use Pstoute\LaravelWorkflows\Models\WorkflowExecution;
use Throwable;

/**
 * @implements Arrayable<string, mixed>
 */
class ExecutionResult implements Arrayable
{
    /**
     * @param array<int, ActionResult> $actionResults
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $status = null,
        public readonly ?string $message = null,
        public readonly array $actionResults = [],
        public readonly ?Throwable $exception = null,
        public readonly ?WorkflowExecution $execution = null,
        public readonly array $metadata = [],
        public readonly ?int $durationMs = null,
    ) {}

    /**
     * Create a successful execution result.
     *
     * @param array<int, ActionResult> $actionResults
     * @param array<string, mixed> $metadata
     */
    public static function success(
        array $actionResults = [],
        ?WorkflowExecution $execution = null,
        ?string $message = null,
        array $metadata = [],
        ?int $durationMs = null,
    ): static {
        return new static(
            success: true,
            status: 'completed',
            message: $message ?? 'Workflow executed successfully',
            actionResults: $actionResults,
            execution: $execution,
            metadata: $metadata,
            durationMs: $durationMs,
        );
    }

    /**
     * Create a failed execution result.
     *
     * @param array<int, ActionResult> $actionResults
     * @param array<string, mixed> $metadata
     */
    public static function failure(
        string $message,
        ?Throwable $exception = null,
        array $actionResults = [],
        ?WorkflowExecution $execution = null,
        array $metadata = [],
        ?int $durationMs = null,
    ): static {
        return new static(
            success: false,
            status: 'failed',
            message: $message,
            actionResults: $actionResults,
            exception: $exception,
            execution: $execution,
            metadata: $metadata,
            durationMs: $durationMs,
        );
    }

    /**
     * Create a skipped execution result (conditions not met).
     */
    public static function skipped(
        string $message = 'Workflow conditions not met',
        ?WorkflowExecution $execution = null,
    ): static {
        return new static(
            success: true,
            status: 'skipped',
            message: $message,
            execution: $execution,
        );
    }

    /**
     * Check if the execution was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if the execution failed.
     */
    public function isFailure(): bool
    {
        return ! $this->success;
    }

    /**
     * Check if the execution was skipped.
     */
    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    /**
     * Get the count of successful actions.
     */
    public function successfulActionsCount(): int
    {
        return count(array_filter(
            $this->actionResults,
            fn (ActionResult $result) => $result->isSuccess()
        ));
    }

    /**
     * Get the count of failed actions.
     */
    public function failedActionsCount(): int
    {
        return count(array_filter(
            $this->actionResults,
            fn (ActionResult $result) => $result->isFailure()
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'message' => $this->message,
            'action_results' => array_map(
                fn (ActionResult $result) => $result->toArray(),
                $this->actionResults
            ),
            'error' => $this->exception?->getMessage(),
            'execution_id' => $this->execution?->id,
            'metadata' => $this->metadata,
            'duration_ms' => $this->durationMs,
        ];
    }
}
