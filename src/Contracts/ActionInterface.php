<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Contracts;

use Pstoute\LaravelWorkflows\Data\ActionResult;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

interface ActionInterface
{
    /**
     * Get the unique identifier for this action type.
     */
    public function getIdentifier(): string;

    /**
     * Get the human-readable name for this action.
     */
    public function getName(): string;

    /**
     * Get the description of what this action does.
     */
    public function getDescription(): string;

    /**
     * Get the JSON schema for configuring this action.
     *
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array;

    /**
     * Execute the action with the given context and configuration.
     *
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult;

    /**
     * Whether this action supports asynchronous execution.
     */
    public function supportsAsync(): bool;

    /**
     * Get the timeout for this action in seconds.
     */
    public function getTimeout(): int;

    /**
     * Get the data keys that this action adds to the context.
     *
     * @return array<string, string> Key => description mapping
     */
    public function getOutputData(): array;
}
