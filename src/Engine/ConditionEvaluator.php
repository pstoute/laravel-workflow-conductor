<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Engine;

use Illuminate\Support\Collection;
use Pstoute\LaravelWorkflows\Contracts\ConditionInterface;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;
use Pstoute\LaravelWorkflows\Exceptions\ConditionException;
use Pstoute\LaravelWorkflows\Models\WorkflowCondition;

class ConditionEvaluator
{
    /**
     * @var array<string, ConditionInterface>
     */
    protected array $conditions = [];

    /**
     * Register a condition type.
     */
    public function register(ConditionInterface $condition): void
    {
        $this->conditions[$condition->getIdentifier()] = $condition;
    }

    /**
     * Get a registered condition by identifier.
     */
    public function get(string $identifier): ?ConditionInterface
    {
        return $this->conditions[$identifier] ?? null;
    }

    /**
     * Get all registered conditions.
     *
     * @return array<string, ConditionInterface>
     */
    public function all(): array
    {
        return $this->conditions;
    }

    /**
     * Evaluate a collection of conditions with AND/OR grouping.
     *
     * @param Collection<int, WorkflowCondition> $conditions
     */
    public function evaluate(Collection $conditions, WorkflowContext $context): bool
    {
        if ($conditions->isEmpty()) {
            return true;
        }

        // Group conditions by their group number
        $groups = $conditions->groupBy('group');

        // Evaluate each group (groups are AND'd together)
        foreach ($groups as $groupConditions) {
            $groupResult = $this->evaluateGroup($groupConditions, $context);

            if (! $groupResult) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a group of conditions (conditions within a group use their logic).
     *
     * @param Collection<int, WorkflowCondition> $conditions
     */
    protected function evaluateGroup(Collection $conditions, WorkflowContext $context): bool
    {
        $result = null;

        foreach ($conditions as $condition) {
            $conditionResult = $this->evaluateSingle($condition, $context);

            if ($result === null) {
                $result = $conditionResult;
                continue;
            }

            if ($condition->isOr()) {
                $result = $result || $conditionResult;
            } else {
                $result = $result && $conditionResult;
            }
        }

        return $result ?? true;
    }

    /**
     * Evaluate a single condition.
     */
    public function evaluateSingle(WorkflowCondition $condition, WorkflowContext $context): bool
    {
        $conditionHandler = $this->get($condition->type);

        if ($conditionHandler === null) {
            throw ConditionException::notFound($condition->type);
        }

        return $conditionHandler->evaluate($context, $condition->toConditionConfig());
    }

    /**
     * Evaluate a condition using a specific operator and value.
     */
    public function evaluateOperator(string $operator, mixed $actualValue, mixed $expectedValue): bool
    {
        return match ($operator) {
            'equals', 'eq', '=', '==' => $this->equals($actualValue, $expectedValue),
            'not_equals', 'neq', '!=', '<>' => ! $this->equals($actualValue, $expectedValue),
            'contains' => $this->contains($actualValue, $expectedValue),
            'not_contains' => ! $this->contains($actualValue, $expectedValue),
            'starts_with' => $this->startsWith($actualValue, $expectedValue),
            'ends_with' => $this->endsWith($actualValue, $expectedValue),
            'greater_than', 'gt', '>' => $this->greaterThan($actualValue, $expectedValue),
            'greater_or_equal', 'gte', '>=' => $this->greaterOrEqual($actualValue, $expectedValue),
            'less_than', 'lt', '<' => $this->lessThan($actualValue, $expectedValue),
            'less_or_equal', 'lte', '<=' => $this->lessOrEqual($actualValue, $expectedValue),
            'is_null', 'null' => $actualValue === null,
            'is_not_null', 'not_null' => $actualValue !== null,
            'is_empty', 'empty' => $this->isEmpty($actualValue),
            'is_not_empty', 'not_empty' => ! $this->isEmpty($actualValue),
            'in' => $this->in($actualValue, $expectedValue),
            'not_in' => ! $this->in($actualValue, $expectedValue),
            'matches_regex', 'regex' => $this->matchesRegex($actualValue, $expectedValue),
            'is_true', 'true' => (bool) $actualValue === true,
            'is_false', 'false' => (bool) $actualValue === false,
            'between' => $this->between($actualValue, $expectedValue),
            default => throw ConditionException::invalidOperator('field', $operator),
        };
    }

    protected function equals(mixed $a, mixed $b): bool
    {
        // Handle numeric comparisons
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        // Handle string comparisons (case-insensitive)
        if (is_string($a) && is_string($b)) {
            return strtolower($a) === strtolower($b);
        }

        return $a === $b;
    }

    protected function contains(mixed $haystack, mixed $needle): bool
    {
        if (is_array($haystack)) {
            return in_array($needle, $haystack, true);
        }

        if (is_string($haystack) && is_string($needle)) {
            return str_contains(strtolower($haystack), strtolower($needle));
        }

        return false;
    }

    protected function startsWith(mixed $value, mixed $prefix): bool
    {
        if (! is_string($value) || ! is_string($prefix)) {
            return false;
        }

        return str_starts_with(strtolower($value), strtolower($prefix));
    }

    protected function endsWith(mixed $value, mixed $suffix): bool
    {
        if (! is_string($value) || ! is_string($suffix)) {
            return false;
        }

        return str_ends_with(strtolower($value), strtolower($suffix));
    }

    protected function greaterThan(mixed $a, mixed $b): bool
    {
        if (! is_numeric($a) || ! is_numeric($b)) {
            return false;
        }

        return (float) $a > (float) $b;
    }

    protected function greaterOrEqual(mixed $a, mixed $b): bool
    {
        if (! is_numeric($a) || ! is_numeric($b)) {
            return false;
        }

        return (float) $a >= (float) $b;
    }

    protected function lessThan(mixed $a, mixed $b): bool
    {
        if (! is_numeric($a) || ! is_numeric($b)) {
            return false;
        }

        return (float) $a < (float) $b;
    }

    protected function lessOrEqual(mixed $a, mixed $b): bool
    {
        if (! is_numeric($a) || ! is_numeric($b)) {
            return false;
        }

        return (float) $a <= (float) $b;
    }

    protected function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return count($value) === 0;
        }

        return empty($value);
    }

    protected function in(mixed $value, mixed $list): bool
    {
        if (! is_array($list)) {
            $list = [$list];
        }

        return in_array($value, $list, false);
    }

    protected function matchesRegex(mixed $value, mixed $pattern): bool
    {
        if (! is_string($value) || ! is_string($pattern)) {
            return false;
        }

        // Add delimiters if not present
        if (! preg_match('/^[\/\#\~\@]/', $pattern)) {
            $pattern = '/' . $pattern . '/i';
        }

        return (bool) preg_match($pattern, $value);
    }

    protected function between(mixed $value, mixed $range): bool
    {
        if (! is_array($range) || count($range) !== 2) {
            return false;
        }

        if (! is_numeric($value)) {
            return false;
        }

        $value = (float) $value;
        $min = (float) $range[0];
        $max = (float) $range[1];

        return $value >= $min && $value <= $max;
    }
}
