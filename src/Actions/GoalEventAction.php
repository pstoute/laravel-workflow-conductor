<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class GoalEventAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'goal';
    }

    public function getName(): string
    {
        return 'Goal / Wait for Event';
    }

    public function getDescription(): string
    {
        return 'Pause workflow execution until a specific event occurs or a timeout is reached';
    }

    public function supportsAsync(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $eventName = $config['event_name'] ?? null;
        $timeoutSeconds = (int) ($config['timeout_seconds'] ?? 86400); // Default 24h
        $timeoutAction = $config['timeout_action'] ?? 'continue'; // continue, fail, goto
        $timeoutNodeId = $config['timeout_node_id'] ?? null;
        $continueNodeId = $config['continue_node_id'] ?? null;

        if ($eventName === null) {
            return ActionResult::failure('No event_name specified for Goal action');
        }

        // Build event conditions for matching
        $eventConditions = $config['event_conditions'] ?? [];

        // Check if the goal has already been met (event already fired)
        $goalMetKey = '_goal_met.' . $eventName;
        if ($context->get($goalMetKey)) {
            return ActionResult::success(
                "Goal '{$eventName}' was already achieved",
                [
                    'event_name' => $eventName,
                    'already_met' => true,
                ],
                $continueNodeId ? ['next_node_id' => $continueNodeId] : []
            );
        }

        // The workflow engine should handle persisting this wait state
        // and resuming when the event fires or timeout occurs
        return ActionResult::success(
            "Waiting for event '{$eventName}' (timeout: {$timeoutSeconds}s)",
            [
                'event_name' => $eventName,
                'event_conditions' => $eventConditions,
                'timeout_seconds' => $timeoutSeconds,
                'timeout_action' => $timeoutAction,
                'timeout_node_id' => $timeoutNodeId,
                'continue_node_id' => $continueNodeId,
                'wait_until' => now()->addSeconds($timeoutSeconds)->toIso8601String(),
            ],
            [
                'wait_for_event' => [
                    'event_name' => $eventName,
                    'conditions' => $eventConditions,
                    'timeout_seconds' => $timeoutSeconds,
                    'timeout_action' => $timeoutAction,
                    'timeout_node_id' => $timeoutNodeId,
                    'continue_node_id' => $continueNodeId,
                ],
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
                'event_name' => [
                    'type' => 'string',
                    'description' => 'The event identifier to wait for (e.g., "payment.received", "form.submitted")',
                    'required' => true,
                ],
                'event_conditions' => [
                    'type' => 'array',
                    'description' => 'Additional conditions the event must match',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'field' => ['type' => 'string', 'description' => 'Event data field to check'],
                            'operator' => ['type' => 'string', 'enum' => ['==', '!=', '>', '<', 'contains']],
                            'value' => ['type' => 'mixed', 'description' => 'Value to compare against'],
                        ],
                    ],
                ],
                'timeout_seconds' => [
                    'type' => 'integer',
                    'description' => 'Seconds to wait before timing out',
                    'default' => 86400,
                ],
                'timeout_action' => [
                    'type' => 'string',
                    'description' => 'What to do when timeout is reached',
                    'enum' => ['continue', 'fail', 'goto'],
                    'default' => 'continue',
                ],
                'timeout_node_id' => [
                    'type' => 'string',
                    'description' => 'Node ID to jump to on timeout (when timeout_action is "goto")',
                ],
                'continue_node_id' => [
                    'type' => 'string',
                    'description' => 'Node ID to jump to when the goal event is received',
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
            'event_name' => 'The event being waited for',
            'timeout_seconds' => 'The timeout duration in seconds',
            'wait_until' => 'ISO 8601 timestamp when the wait will expire',
        ];
    }
}
