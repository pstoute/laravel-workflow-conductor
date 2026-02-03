<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Facades;

use Illuminate\Support\Facades\Facade;
use Pstoute\WorkflowConductor\Contracts\ActionInterface;
use Pstoute\WorkflowConductor\Contracts\ConditionInterface;
use Pstoute\WorkflowConductor\Contracts\TriggerInterface;
use Pstoute\WorkflowConductor\Data\ExecutionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;
use Pstoute\WorkflowConductor\WorkflowBuilder;
use Pstoute\WorkflowConductor\WorkflowManager;

/**
 * @method static void registerTrigger(TriggerInterface $trigger)
 * @method static void registerCondition(ConditionInterface $condition)
 * @method static void registerAction(ActionInterface $action)
 * @method static TriggerInterface|null getTrigger(string $identifier)
 * @method static ConditionInterface|null getCondition(string $identifier)
 * @method static ActionInterface|null getAction(string $identifier)
 * @method static array<string, TriggerInterface> getTriggers()
 * @method static array<string, ConditionInterface> getConditions()
 * @method static array<string, ActionInterface> getActions()
 * @method static ExecutionResult execute(int $workflowId, WorkflowContext $context)
 * @method static void executeAsync(int $workflowId, WorkflowContext $context)
 * @method static void trigger(string $triggerType, WorkflowContext $context)
 * @method static WorkflowBuilder create()
 * @method static \Illuminate\Database\Eloquent\Builder executions()
 *
 * @see \Pstoute\WorkflowConductor\WorkflowManager
 */
class Conductor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WorkflowManager::class;
    }
}
