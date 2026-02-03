<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Engine;

use Illuminate\Support\Collection;
use Pstoute\WorkflowConductor\Contracts\TriggerInterface;
use Pstoute\WorkflowConductor\Data\WorkflowContext;
use Pstoute\WorkflowConductor\Exceptions\TriggerException;
use Pstoute\WorkflowConductor\Models\Workflow;
use Pstoute\WorkflowConductor\Models\WorkflowTrigger;

class TriggerManager
{
    /**
     * @var array<string, TriggerInterface>
     */
    protected array $triggers = [];

    /**
     * Register a trigger type.
     */
    public function register(TriggerInterface $trigger): void
    {
        $this->triggers[$trigger->getIdentifier()] = $trigger;
    }

    /**
     * Get a registered trigger by identifier.
     */
    public function get(string $identifier): ?TriggerInterface
    {
        return $this->triggers[$identifier] ?? null;
    }

    /**
     * Get all registered triggers.
     *
     * @return array<string, TriggerInterface>
     */
    public function all(): array
    {
        return $this->triggers;
    }

    /**
     * Find workflows that should be triggered for the given trigger type and context.
     *
     * @return Collection<int, Workflow>
     */
    public function findMatchingWorkflows(string $triggerType, WorkflowContext $context): Collection
    {
        $trigger = $this->get($triggerType);

        if ($trigger === null) {
            throw TriggerException::notFound($triggerType);
        }

        return Workflow::query()
            ->active()
            ->withTriggerType($triggerType)
            ->with(['triggers' => function ($query) use ($triggerType) {
                $query->where('type', $triggerType);
            }])
            ->get()
            ->filter(function (Workflow $workflow) use ($trigger, $context) {
                // Check if any trigger configuration matches
                return $workflow->triggers->some(function (WorkflowTrigger $workflowTrigger) use ($trigger, $context) {
                    return $trigger->shouldTrigger($context, $workflowTrigger->configuration ?? []);
                });
            });
    }

    /**
     * Check if a specific workflow should be triggered.
     */
    public function shouldTrigger(WorkflowTrigger $trigger, WorkflowContext $context): bool
    {
        $triggerHandler = $this->get($trigger->type);

        if ($triggerHandler === null) {
            throw TriggerException::notFound($trigger->type);
        }

        return $triggerHandler->shouldTrigger($context, $trigger->configuration ?? []);
    }

    /**
     * Get available data keys for a trigger type.
     *
     * @return array<string, string>
     */
    public function getAvailableData(string $triggerType): array
    {
        $trigger = $this->get($triggerType);

        return $trigger?->getAvailableData() ?? [];
    }

    /**
     * Get the configuration schema for a trigger type.
     *
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(string $triggerType): array
    {
        $trigger = $this->get($triggerType);

        return $trigger?->getConfigurationSchema() ?? [];
    }
}
