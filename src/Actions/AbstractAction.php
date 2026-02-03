<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Pstoute\WorkflowConductor\Contracts\ActionInterface;

abstract class AbstractAction implements ActionInterface
{
    public function supportsAsync(): bool
    {
        return true;
    }

    public function getTimeout(): int
    {
        return config('workflow-conductor.execution.timeout', 300);
    }

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
    public function getOutputData(): array
    {
        return [];
    }
}
