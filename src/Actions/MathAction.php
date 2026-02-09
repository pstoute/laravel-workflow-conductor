<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class MathAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'math';
    }

    public function getName(): string
    {
        return 'Math Operation';
    }

    public function getDescription(): string
    {
        return 'Perform mathematical operations on values';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $operation = $config['operation'] ?? null;

        if ($operation === null) {
            return ActionResult::failure('No math operation specified');
        }

        try {
            $result = match ($operation) {
                'add' => $this->binaryOp($config, $context, fn ($a, $b) => $a + $b),
                'subtract' => $this->binaryOp($config, $context, fn ($a, $b) => $a - $b),
                'multiply' => $this->binaryOp($config, $context, fn ($a, $b) => $a * $b),
                'divide' => $this->divide($config, $context),
                'modulo' => $this->modulo($config, $context),
                'power' => $this->binaryOp($config, $context, fn ($a, $b) => $a ** $b),
                'sqrt' => $this->unaryOp($config, $context, fn ($v) => sqrt($v)),
                'abs' => $this->unaryOp($config, $context, fn ($v) => abs($v)),
                'round' => $this->round($config, $context),
                'ceil' => $this->unaryOp($config, $context, fn ($v) => ceil($v)),
                'floor' => $this->unaryOp($config, $context, fn ($v) => floor($v)),
                'min' => $this->aggregateOp($config, $context, fn ($values) => min($values)),
                'max' => $this->aggregateOp($config, $context, fn ($values) => max($values)),
                'sum' => $this->aggregateOp($config, $context, fn ($values) => array_sum($values)),
                'average' => $this->aggregateOp($config, $context, fn ($values) => count($values) > 0 ? array_sum($values) / count($values) : 0),
                default => null,
            };

            if ($result === null) {
                return ActionResult::failure("Unknown math operation: {$operation}");
            }

            // Store result in context if output_key is specified
            $outputKey = $config['output_key'] ?? null;
            if ($outputKey) {
                $context->set($outputKey, $result);
            }

            return ActionResult::success("Math operation '{$operation}' completed", [
                'result' => $result,
                'operation' => $operation,
            ]);
        } catch (\Throwable $e) {
            return ActionResult::failure("Math operation failed: {$e->getMessage()}", $e);
        }
    }

    protected function resolveValue(array $config, WorkflowContext $context, string $key): float|int
    {
        $value = $config[$key] ?? null;

        if ($value === null) {
            throw new \InvalidArgumentException("Missing required parameter: {$key}");
        }

        // If value is a string starting with {{, resolve from context
        if (is_string($value) && str_starts_with($value, '{{') && str_ends_with($value, '}}')) {
            $contextKey = trim($value, '{} ');
            $resolved = $context->get($contextKey);

            if ($resolved === null) {
                throw new \InvalidArgumentException("Context key '{$contextKey}' not found");
            }

            return is_numeric($resolved) ? (float) $resolved : throw new \InvalidArgumentException("Context value for '{$contextKey}' is not numeric");
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("Value for '{$key}' is not numeric: {$value}");
        }

        return (float) $value;
    }

    protected function binaryOp(array $config, WorkflowContext $context, callable $op): float|int
    {
        $a = $this->resolveValue($config, $context, 'value_a');
        $b = $this->resolveValue($config, $context, 'value_b');

        return $op($a, $b);
    }

    protected function unaryOp(array $config, WorkflowContext $context, callable $op): float|int
    {
        $value = $this->resolveValue($config, $context, 'value');

        return $op($value);
    }

    protected function divide(array $config, WorkflowContext $context): float|int
    {
        $a = $this->resolveValue($config, $context, 'value_a');
        $b = $this->resolveValue($config, $context, 'value_b');

        if ($b == 0) {
            throw new \DivisionByZeroError('Division by zero');
        }

        return $a / $b;
    }

    protected function modulo(array $config, WorkflowContext $context): float|int
    {
        $a = $this->resolveValue($config, $context, 'value_a');
        $b = $this->resolveValue($config, $context, 'value_b');

        if ($b == 0) {
            throw new \DivisionByZeroError('Modulo by zero');
        }

        return fmod($a, $b);
    }

    protected function round(array $config, WorkflowContext $context): float|int
    {
        $value = $this->resolveValue($config, $context, 'value');
        $precision = (int) ($config['precision'] ?? 0);

        return round($value, $precision);
    }

    protected function aggregateOp(array $config, WorkflowContext $context, callable $op): float|int
    {
        $values = $config['values'] ?? [];

        if (is_string($values) && str_starts_with($values, '{{') && str_ends_with($values, '}}')) {
            $contextKey = trim($values, '{} ');
            $values = $context->get($contextKey, []);
        }

        if (!is_array($values) || empty($values)) {
            throw new \InvalidArgumentException('Aggregate operations require a non-empty array of values');
        }

        // Ensure all values are numeric
        $numericValues = array_map(function ($v) {
            if (!is_numeric($v)) {
                throw new \InvalidArgumentException("Non-numeric value in array: {$v}");
            }
            return (float) $v;
        }, $values);

        return $op($numericValues);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'description' => 'The math operation to perform',
                    'enum' => ['add', 'subtract', 'multiply', 'divide', 'modulo', 'power', 'sqrt', 'abs', 'round', 'ceil', 'floor', 'min', 'max', 'sum', 'average'],
                    'required' => true,
                ],
                'value_a' => [
                    'type' => ['number', 'string'],
                    'description' => 'First operand (for binary ops). Use {{context.key}} for context values.',
                ],
                'value_b' => [
                    'type' => ['number', 'string'],
                    'description' => 'Second operand (for binary ops). Use {{context.key}} for context values.',
                ],
                'value' => [
                    'type' => ['number', 'string'],
                    'description' => 'Single operand (for unary ops like sqrt, abs). Use {{context.key}} for context values.',
                ],
                'values' => [
                    'type' => ['array', 'string'],
                    'description' => 'Array of values (for aggregate ops like min, max, sum). Use {{context.key}} for context array.',
                ],
                'precision' => [
                    'type' => 'integer',
                    'description' => 'Decimal precision for round operation',
                    'default' => 0,
                ],
                'output_key' => [
                    'type' => 'string',
                    'description' => 'Context key to store the result in',
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
            'result' => 'The result of the math operation',
            'operation' => 'The operation that was performed',
        ];
    }
}
