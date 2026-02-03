<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Contracts;

use Pstoute\LaravelWorkflows\Data\WorkflowContext;

interface TriggerInterface
{
    /**
     * Get the unique identifier for this trigger type.
     */
    public function getIdentifier(): string;

    /**
     * Get the human-readable name for this trigger.
     */
    public function getName(): string;

    /**
     * Get the description of what this trigger does.
     */
    public function getDescription(): string;

    /**
     * Get the JSON schema for configuring this trigger.
     *
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array;

    /**
     * Determine if the trigger should fire based on the context.
     *
     * @param array<string, mixed> $triggerConfig
     */
    public function shouldTrigger(WorkflowContext $context, array $triggerConfig): bool;

    /**
     * Get the data keys that will be available after this trigger fires.
     *
     * @return array<string, string> Key => description mapping
     */
    public function getAvailableData(): array;
}
