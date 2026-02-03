<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Conditions;

use Pstoute\LaravelWorkflows\Contracts\ConditionInterface;

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
