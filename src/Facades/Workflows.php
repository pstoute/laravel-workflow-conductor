<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Facades;

use Illuminate\Support\Facades\Facade;
use Pstoute\LaravelWorkflows\Contracts\ActionInterface;
use Pstoute\LaravelWorkflows\Contracts\ConditionInterface;
use Pstoute\LaravelWorkflows\Contracts\TriggerInterface;
use Pstoute\LaravelWorkflows\Data\ExecutionResult;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;
use Pstoute\LaravelWorkflows\WorkflowBuilder;
use Pstoute\LaravelWorkflows\WorkflowManager;

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
 * @see \Pstoute\LaravelWorkflows\WorkflowManager
 */
class Workflows extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WorkflowManager::class;
    }
}
