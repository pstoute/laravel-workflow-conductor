<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Conditions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class RelationCondition extends AbstractCondition
{
    public function getIdentifier(): string
    {
        return 'relation';
    }

    public function getName(): string
    {
        return 'Relation Condition';
    }

    public function getDescription(): string
    {
        return 'Check conditions on model relationships';
    }

    /**
     * @return array<string, string>
     */
    public function getOperators(): array
    {
        return [
            'exists' => 'Has Related Records',
            'not_exists' => 'Has No Related Records',
            'count_equals' => 'Related Count Equals',
            'count_greater' => 'Related Count Greater Than',
            'count_less' => 'Related Count Less Than',
            'count_between' => 'Related Count Between',
            'has_where' => 'Has Related Where',
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public function evaluate(WorkflowContext $context, array $config): bool
    {
        $modelPath = $config['model'] ?? 'model';
        $relation = $config['relation'] ?? null;
        $operator = $config['operator'] ?? 'exists';
        $value = $config['value'] ?? null;

        if ($relation === null) {
            return false;
        }

        $model = $context->get($modelPath);

        if (! $model instanceof Model) {
            return false;
        }

        if (! method_exists($model, $relation)) {
            return false;
        }

        $relationInstance = $model->{$relation}();

        if (! $relationInstance instanceof Relation) {
            return false;
        }

        return match ($operator) {
            'exists' => $this->hasRelation($model, $relation),
            'not_exists' => ! $this->hasRelation($model, $relation),
            'count_equals' => $this->countEquals($model, $relation, $value),
            'count_greater' => $this->countGreaterThan($model, $relation, $value),
            'count_less' => $this->countLessThan($model, $relation, $value),
            'count_between' => $this->countBetween($model, $relation, $value),
            'has_where' => $this->hasWhere($model, $relation, $value),
            default => false,
        };
    }

    protected function hasRelation(Model $model, string $relation): bool
    {
        return $model->{$relation}()->exists();
    }

    protected function countEquals(Model $model, string $relation, mixed $value): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        return $model->{$relation}()->count() === (int) $value;
    }

    protected function countGreaterThan(Model $model, string $relation, mixed $value): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        return $model->{$relation}()->count() > (int) $value;
    }

    protected function countLessThan(Model $model, string $relation, mixed $value): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        return $model->{$relation}()->count() < (int) $value;
    }

    protected function countBetween(Model $model, string $relation, mixed $value): bool
    {
        if (! is_array($value) || count($value) !== 2) {
            return false;
        }

        $count = $model->{$relation}()->count();

        return $count >= (int) $value[0] && $count <= (int) $value[1];
    }

    /**
     * @param array<string, mixed>|null $conditions
     */
    protected function hasWhere(Model $model, string $relation, mixed $conditions): bool
    {
        if (! is_array($conditions) || empty($conditions)) {
            return false;
        }

        $query = $model->{$relation}();

        foreach ($conditions as $field => $value) {
            if (is_array($value) && count($value) === 2) {
                $query->where($field, $value[0], $value[1]);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'model' => [
                    'type' => 'string',
                    'description' => 'The context path to the model (default: "model")',
                    'default' => 'model',
                ],
                'relation' => [
                    'type' => 'string',
                    'description' => 'The name of the relationship method',
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
                    'description' => 'The value for count comparisons or where conditions',
                ],
            ],
        ];
    }
}
