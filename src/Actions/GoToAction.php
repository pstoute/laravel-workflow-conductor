<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class GoToAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'goto';
    }

    public function getName(): string
    {
        return 'Go To';
    }

    public function getDescription(): string
    {
        return 'Jump to a specific action node in the workflow';
    }

    public function supportsAsync(): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $targetNodeId = $config['target_node_id'] ?? null;

        if ($targetNodeId === null) {
            return ActionResult::failure('No target_node_id specified for GoTo action');
        }

        // Resolve dynamic target from context
        if (is_string($targetNodeId) && str_starts_with($targetNodeId, '{{') && str_ends_with($targetNodeId, '}}')) {
            $contextKey = trim($targetNodeId, '{} ');
            $targetNodeId = $context->get($contextKey);

            if ($targetNodeId === null) {
                return ActionResult::failure("Context key '{$contextKey}' not found for target node");
            }
        }

        // Track GoTo count to prevent infinite loops
        $gotoCountKey = '_goto_count.' . $targetNodeId;
        $currentCount = (int) $context->get($gotoCountKey, 0);
        $maxLoops = (int) ($config['max_loops'] ?? 100);

        if ($currentCount >= $maxLoops) {
            return ActionResult::failure(
                "GoTo loop limit reached ({$maxLoops}) for node '{$targetNodeId}'. Possible infinite loop."
            );
        }

        $context->set($gotoCountKey, $currentCount + 1);

        return ActionResult::success(
            "Jumping to node '{$targetNodeId}'",
            [
                'target_node_id' => $targetNodeId,
                'loop_count' => $currentCount + 1,
            ],
            [
                'next_node_id' => $targetNodeId,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'target_node_id' => [
                    'type' => 'string',
                    'description' => 'The node ID to jump to. Use {{context.key}} for dynamic targets.',
                    'required' => true,
                ],
                'max_loops' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of times this GoTo can jump to the same target (infinite loop protection)',
                    'default' => 100,
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getOutputData(): array
    {
        return [
            'target_node_id' => 'The node ID that was jumped to',
            'loop_count' => 'How many times this target has been visited',
        ];
    }
}
