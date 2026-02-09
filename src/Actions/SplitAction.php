<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class SplitAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'split';
    }

    public function getName(): string
    {
        return 'Split';
    }

    public function getDescription(): string
    {
        return 'Split workflow execution into multiple parallel branches';
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
        $branches = $config['branches'] ?? [];

        if (empty($branches)) {
            return ActionResult::failure('No branches specified for Split action');
        }

        $branchNodeIds = [];
        $branchDescriptions = [];

        foreach ($branches as $branch) {
            $nodeId = $branch['node_id'] ?? null;

            if ($nodeId === null) {
                return ActionResult::failure('Each branch must have a "node_id"');
            }

            // Optionally check a condition before including the branch
            if (isset($branch['condition'])) {
                $field = $branch['condition']['field'] ?? null;
                $operator = $branch['condition']['operator'] ?? '==';
                $value = $branch['condition']['value'] ?? null;

                if ($field !== null) {
                    $fieldValue = $context->get($field);

                    $passed = match ($operator) {
                        '==' => $fieldValue == $value,
                        '!=' => $fieldValue != $value,
                        'is_true' => (bool) $fieldValue === true,
                        'is_false' => (bool) $fieldValue === false,
                        'is_not_empty' => !empty($fieldValue),
                        default => true,
                    };

                    if (!$passed) {
                        continue; // Skip this branch
                    }
                }
            }

            $branchNodeIds[] = $nodeId;
            $branchDescriptions[] = $branch['name'] ?? $nodeId;
        }

        if (empty($branchNodeIds)) {
            return ActionResult::success('No branches matched conditions', [
                'branch_count' => 0,
                'branch_node_ids' => [],
            ]);
        }

        return ActionResult::success(
            count($branchNodeIds) . ' parallel branch(es) dispatched',
            [
                'branch_count' => count($branchNodeIds),
                'branch_node_ids' => $branchNodeIds,
                'branch_names' => $branchDescriptions,
            ],
            [
                'branch_node_ids' => $branchNodeIds,
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
                'branches' => [
                    'type' => 'array',
                    'description' => 'Array of branches to execute in parallel',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'node_id' => [
                                'type' => 'string',
                                'description' => 'The starting node ID for this branch',
                                'required' => true,
                            ],
                            'name' => [
                                'type' => 'string',
                                'description' => 'Human-readable name for this branch',
                            ],
                            'condition' => [
                                'type' => 'object',
                                'description' => 'Optional condition to check before executing this branch',
                                'properties' => [
                                    'field' => ['type' => 'string'],
                                    'operator' => ['type' => 'string', 'enum' => ['==', '!=', 'is_true', 'is_false', 'is_not_empty']],
                                    'value' => ['type' => 'mixed'],
                                ],
                            ],
                        ],
                    ],
                    'required' => true,
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
            'branch_count' => 'Number of branches dispatched',
            'branch_node_ids' => 'Array of node IDs for each branch',
            'branch_names' => 'Human-readable names for each branch',
        ];
    }
}
