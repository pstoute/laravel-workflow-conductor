<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Triggers;

use Pstoute\LaravelWorkflows\Contracts\TriggerInterface;

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
