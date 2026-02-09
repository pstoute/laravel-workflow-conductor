<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class IfElseAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'if_else';
    }

    public function getName(): string
    {
        return 'If / Else';
    }

    public function getDescription(): string
    {
        return 'Evaluate conditions and route to different branches based on the result';
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
        $conditions = $config['conditions'] ?? [];
        $logicOperator = $config['logic'] ?? 'and'; // and, or
        $trueNodeId = $config['true_node_id'] ?? null;
        $falseNodeId = $config['false_node_id'] ?? null;

        if (empty($conditions)) {
            return ActionResult::failure('No conditions specified for If/Else action');
        }

        try {
            $results = [];

            foreach ($conditions as $condition) {
                $results[] = $this->evaluateCondition($condition, $context);
            }

            $passed = $logicOperator === 'and'
                ? !in_array(false, $results, true)
                : in_array(true, $results, true);

            $nextNodeId = $passed ? $trueNodeId : $falseNodeId;
            $branch = $passed ? 'true' : 'false';

            $metadata = [];
            if ($nextNodeId) {
                $metadata['next_node_id'] = $nextNodeId;
            }

            return ActionResult::success(
                "Condition evaluated to {$branch}",
                [
                    'result' => $passed,
                    'branch' => $branch,
                    'condition_results' => $results,
                    'next_node_id' => $nextNodeId,
                ],
                $metadata
            );
        } catch (\Throwable $e) {
            return ActionResult::failure("If/Else evaluation failed: {$e->getMessage()}", $e);
        }
    }

    protected function evaluateCondition(array $condition, WorkflowContext $context): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? '==';
        $value = $condition['value'] ?? null;

        if ($field === null) {
            throw new \InvalidArgumentException('Condition is missing "field"');
        }

        $fieldValue = $context->get($field);

        // Resolve value from context if needed
        if (is_string($value) && str_starts_with($value, '{{') && str_ends_with($value, '}}')) {
            $contextKey = trim($value, '{} ');
            $value = $context->get($contextKey);
        }

        return match ($operator) {
            '==' => $fieldValue == $value,
            '===' => $fieldValue === $value,
            '!=' => $fieldValue != $value,
            '!==' => $fieldValue !== $value,
            '>' => $fieldValue > $value,
            '>=' => $fieldValue >= $value,
            '<' => $fieldValue < $value,
            '<=' => $fieldValue <= $value,
            'contains' => is_string($fieldValue) && str_contains($fieldValue, (string) $value),
            'not_contains' => is_string($fieldValue) && !str_contains($fieldValue, (string) $value),
            'starts_with' => is_string($fieldValue) && str_starts_with($fieldValue, (string) $value),
            'ends_with' => is_string($fieldValue) && str_ends_with($fieldValue, (string) $value),
            'in' => is_array($value) && in_array($fieldValue, $value),
            'not_in' => is_array($value) && !in_array($fieldValue, $value),
            'is_empty' => empty($fieldValue),
            'is_not_empty' => !empty($fieldValue),
            'is_null' => $fieldValue === null,
            'is_not_null' => $fieldValue !== null,
            'matches' => is_string($fieldValue) && is_string($value) && (bool) preg_match($value, $fieldValue),
            'is_true' => (bool) $fieldValue === true,
            'is_false' => (bool) $fieldValue === false,
            default => throw new \InvalidArgumentException("Unknown operator: {$operator}"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'conditions' => [
                    'type' => 'array',
                    'description' => 'Array of conditions to evaluate',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'field' => [
                                'type' => 'string',
                                'description' => 'Context key to check (dot notation)',
                                'required' => true,
                            ],
                            'operator' => [
                                'type' => 'string',
                                'description' => 'Comparison operator',
                                'enum' => [
                                    '==', '===', '!=', '!==', '>', '>=', '<', '<=',
                                    'contains', 'not_contains', 'starts_with', 'ends_with',
                                    'in', 'not_in', 'is_empty', 'is_not_empty',
                                    'is_null', 'is_not_null', 'matches', 'is_true', 'is_false',
                                ],
                                'default' => '==',
                            ],
                            'value' => [
                                'type' => 'mixed',
                                'description' => 'Value to compare against. Use {{context.key}} for context values.',
                            ],
                        ],
                    ],
                    'required' => true,
                ],
                'logic' => [
                    'type' => 'string',
                    'description' => 'How to combine multiple conditions',
                    'enum' => ['and', 'or'],
                    'default' => 'and',
                ],
                'true_node_id' => [
                    'type' => 'string',
                    'description' => 'Node ID to jump to if conditions evaluate to true',
                ],
                'false_node_id' => [
                    'type' => 'string',
                    'description' => 'Node ID to jump to if conditions evaluate to false',
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
            'result' => 'Boolean result of the condition evaluation',
            'branch' => 'Which branch was taken: "true" or "false"',
            'condition_results' => 'Individual results for each condition',
            'next_node_id' => 'The node ID to route to next',
        ];
    }
}
