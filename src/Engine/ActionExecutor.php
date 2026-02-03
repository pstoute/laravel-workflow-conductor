<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Engine;

use Pstoute\WorkflowConductor\Contracts\ActionInterface;
use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;
use Pstoute\WorkflowConductor\Exceptions\ActionException;
use Pstoute\WorkflowConductor\Models\WorkflowAction;
use Pstoute\WorkflowConductor\Support\VariableInterpolator;

class ActionExecutor
{
    /**
     * @var array<string, ActionInterface>
     */
    protected array $actions = [];

    protected VariableInterpolator $interpolator;

    public function __construct(?VariableInterpolator $interpolator = null)
    {
        $this->interpolator = $interpolator ?? new VariableInterpolator();
    }

    /**
     * Register an action type.
     */
    public function register(ActionInterface $action): void
    {
        $this->actions[$action->getIdentifier()] = $action;
    }

    /**
     * Get a registered action by identifier.
     */
    public function get(string $identifier): ?ActionInterface
    {
        return $this->actions[$identifier] ?? null;
    }

    /**
     * Get all registered actions.
     *
     * @return array<string, ActionInterface>
     */
    public function all(): array
    {
        return $this->actions;
    }

    /**
     * Execute a workflow action.
     */
    public function execute(WorkflowAction $workflowAction, WorkflowContext $context): ActionResult
    {
        $action = $this->get($workflowAction->type);

        if ($action === null) {
            throw ActionException::notFound($workflowAction->type);
        }

        // Interpolate variables in the configuration
        $config = $this->interpolator->interpolate(
            $workflowAction->configuration ?? [],
            $context
        );

        try {
            $result = $action->execute($context, $config);

            // Merge action output into context for subsequent actions
            if ($result->isSuccess() && ! empty($result->output)) {
                $context->merge($result->output);
            }

            return $result;
        } catch (\Throwable $e) {
            throw ActionException::executionFailed(
                $workflowAction->type,
                $e->getMessage(),
                $workflowAction,
                $e
            );
        }
    }

    /**
     * Execute an action directly by identifier.
     *
     * @param array<string, mixed> $config
     */
    public function executeByType(string $type, WorkflowContext $context, array $config): ActionResult
    {
        $action = $this->get($type);

        if ($action === null) {
            throw ActionException::notFound($type);
        }

        // Interpolate variables in the configuration
        $config = $this->interpolator->interpolate($config, $context);

        try {
            $result = $action->execute($context, $config);

            // Merge action output into context
            if ($result->isSuccess() && ! empty($result->output)) {
                $context->merge($result->output);
            }

            return $result;
        } catch (\Throwable $e) {
            throw ActionException::executionFailed(
                $type,
                $e->getMessage(),
                null,
                $e
            );
        }
    }

    /**
     * Check if an action supports async execution.
     */
    public function supportsAsync(string $type): bool
    {
        $action = $this->get($type);

        return $action?->supportsAsync() ?? false;
    }

    /**
     * Get the timeout for an action.
     */
    public function getTimeout(string $type): int
    {
        $action = $this->get($type);

        return $action?->getTimeout() ?? config('workflow-conductor.execution.timeout', 300);
    }
}
