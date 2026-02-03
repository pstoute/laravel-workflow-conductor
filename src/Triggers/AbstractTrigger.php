<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Triggers;

use Pstoute\WorkflowConductor\Contracts\TriggerInterface;

abstract class AbstractTrigger implements TriggerInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableData(): array
    {
        return [];
    }
}
