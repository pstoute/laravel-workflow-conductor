<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Data;

use Illuminate\Contracts\Support\Arrayable;
use Throwable;

/**
 * @implements Arrayable<string, mixed>
 */
class ActionResult implements Arrayable
{
    /**
     * @param array<string, mixed> $output
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = null,
        public readonly array $output = [],
        public readonly ?Throwable $exception = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a successful result.
     *
     * @param array<string, mixed> $output
     * @param array<string, mixed> $metadata
     */
    public static function success(
        ?string $message = null,
        array $output = [],
        array $metadata = []
    ): static {
        return new static(
            success: true,
            message: $message,
            output: $output,
            metadata: $metadata,
        );
    }

    /**
     * Create a failed result.
     *
     * @param array<string, mixed> $metadata
     */
    public static function failure(
        string $message,
        ?Throwable $exception = null,
        array $metadata = []
    ): static {
        return new static(
            success: false,
            message: $message,
            exception: $exception,
            metadata: $metadata,
        );
    }

    /**
     * Check if the action was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if the action failed.
     */
    public function isFailure(): bool
    {
        return ! $this->success;
    }

    /**
     * Get a specific output value.
     */
    public function getOutput(string $key, mixed $default = null): mixed
    {
        return data_get($this->output, $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'output' => $this->output,
            'error' => $this->exception?->getMessage(),
            'metadata' => $this->metadata,
        ];
    }
}
