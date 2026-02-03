<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Conditions;

use Pstoute\LaravelWorkflows\Data\WorkflowContext;
use Pstoute\LaravelWorkflows\Engine\ConditionEvaluator;

class FieldCondition extends AbstractCondition
{
    public function getIdentifier(): string
    {
        return 'field';
    }

    public function getName(): string
    {
        return 'Field Condition';
    }

    public function getDescription(): string
    {
        return 'Compare a field value using various operators';
    }

    /**
     * @return array<string, string>
     */
    public function getOperators(): array
    {
        return [
            'equals' => 'Equals',
            'not_equals' => 'Not Equals',
            'contains' => 'Contains',
            'not_contains' => 'Does Not Contain',
            'starts_with' => 'Starts With',
            'ends_with' => 'Ends With',
            'greater_than' => 'Greater Than',
            'greater_or_equal' => 'Greater Than or Equal',
            'less_than' => 'Less Than',
            'less_or_equal' => 'Less Than or Equal',
            'is_null' => 'Is Null',
            'is_not_null' => 'Is Not Null',
            'is_empty' => 'Is Empty',
            'is_not_empty' => 'Is Not Empty',
            'in' => 'In List',
            'not_in' => 'Not In List',
            'matches_regex' => 'Matches Regex',
            'is_true' => 'Is True',
            'is_false' => 'Is False',
            'between' => 'Between',
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public function evaluate(WorkflowContext $context, array $config): bool
    {
        $field = $config['field'] ?? null;
        $operator = $config['operator'] ?? 'equals';
        $expectedValue = $config['value'] ?? null;

        if ($field === null) {
            return false;
        }

        $actualValue = $context->get($field);

        $evaluator = new ConditionEvaluator();

        return $evaluator->evaluateOperator($operator, $actualValue, $expectedValue);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'field' => [
                    'type' => 'string',
                    'description' => 'The field path to evaluate (dot notation supported)',
                    'required' => true,
                ],
                'operator' => [
                    'type' => 'string',
                    'description' => 'The comparison operator',
                    'enum' => array_keys($this->getOperators()),
                    'required' => true,
                ],
                'value' => [
                    'type' => 'mixed',
                    'description' => 'The value to compare against',
                ],
            ],
        ];
    }
}
