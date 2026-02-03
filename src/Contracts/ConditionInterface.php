<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Contracts;

use Pstoute\LaravelWorkflows\Data\WorkflowContext;

interface ConditionInterface
{
    /**
     * Get the unique identifier for this condition type.
     */
    public function getIdentifier(): string;

    /**
     * Get the human-readable name for this condition.
     */
    public function getName(): string;

    /**
     * Get the description of what this condition checks.
     */
    public function getDescription(): string;

    /**
     * Get the available operators for this condition type.
     *
     * @return array<string, string> Operator key => description
     */
    public function getOperators(): array;

    /**
     * Evaluate the condition against the workflow context.
     *
     * @param array<string, mixed> $config
     */
    public function evaluate(WorkflowContext $context, array $config): bool;

    /**
     * Get the JSON schema for configuring this condition.
     *
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array;
}
