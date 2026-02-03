<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Conditions;

use Pstoute\WorkflowConductor\Contracts\ConditionInterface;

abstract class AbstractCondition implements ConditionInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [];
    }
}
