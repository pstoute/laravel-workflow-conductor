<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Illuminate\Support\Arr;
use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class ArrayAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'array';
    }

    public function getName(): string
    {
        return 'Array Functions';
    }

    public function getDescription(): string
    {
        return 'Perform operations on arrays: push, pop, filter, map, merge, count, unique, sort, flatten';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $operation = $config['operation'] ?? null;

        if ($operation === null) {
            return ActionResult::failure('No array operation specified');
        }

        try {
            $result = match ($operation) {
                'push' => $this->push($config, $context),
                'pop' => $this->pop($config, $context),
                'shift' => $this->shift($config, $context),
                'unshift' => $this->unshift($config, $context),
                'filter' => $this->filter($config, $context),
                'map' => $this->map($config, $context),
                'merge' => $this->merge($config, $context),
                'count' => $this->count($config, $context),
                'unique' => $this->unique($config, $context),
                'sort' => $this->sort($config, $context),
                'reverse' => $this->reverse($config, $context),
                'flatten' => $this->flatten($config, $context),
                'pluck' => $this->pluck($config, $context),
                'slice' => $this->slice($config, $context),
                'chunk' => $this->chunk($config, $context),
                'first' => $this->first($config, $context),
                'last' => $this->last($config, $context),
                'join' => $this->join($config, $context),
                'contains' => $this->contains($config, $context),
                'index_of' => $this->indexOf($config, $context),
                'keys' => $this->keys($config, $context),
                'values' => $this->values($config, $context),
                'get' => $this->getFromArray($config, $context),
                'set' => $this->setInArray($config, $context),
                'remove' => $this->removeFromArray($config, $context),
                default => null,
            };

            if ($result === null) {
                return ActionResult::failure("Unknown array operation: {$operation}");
            }

            $outputKey = $config['output_key'] ?? null;
            if ($outputKey) {
                $context->set($outputKey, $result['value']);
            }

            return ActionResult::success("Array operation '{$operation}' completed", $result);
        } catch (\Throwable $e) {
            return ActionResult::failure("Array operation failed: {$e->getMessage()}", $e);
        }
    }

    protected function resolveArray(array $config, WorkflowContext $context, string $key = 'array'): array
    {
        $value = $config[$key] ?? [];

        if (is_string($value) && str_starts_with($value, '{{') && str_ends_with($value, '}}')) {
            $contextKey = trim($value, '{} ');
            $value = $context->get($contextKey, []);
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException("Expected array for '{$key}', got " . gettype($value));
        }

        return $value;
    }

    protected function push(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $item = $config['item'] ?? null;
        $arr[] = $item;

        return ['value' => $arr, 'count' => count($arr)];
    }

    protected function pop(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $popped = array_pop($arr);

        return ['value' => $arr, 'removed' => $popped, 'count' => count($arr)];
    }

    protected function shift(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $shifted = array_shift($arr);

        return ['value' => array_values($arr), 'removed' => $shifted, 'count' => count($arr)];
    }

    protected function unshift(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $item = $config['item'] ?? null;
        array_unshift($arr, $item);

        return ['value' => $arr, 'count' => count($arr)];
    }

    protected function filter(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $filterKey = $config['filter_key'] ?? null;
        $filterValue = $config['filter_value'] ?? null;
        $operator = $config['operator'] ?? '==';

        if ($filterKey === null) {
            // Remove falsy values
            $result = array_values(array_filter($arr));
        } else {
            $result = array_values(array_filter($arr, function ($item) use ($filterKey, $filterValue, $operator) {
                $itemValue = is_array($item) ? ($item[$filterKey] ?? null) : null;

                return match ($operator) {
                    '==' => $itemValue == $filterValue,
                    '===' => $itemValue === $filterValue,
                    '!=' => $itemValue != $filterValue,
                    '>' => $itemValue > $filterValue,
                    '>=' => $itemValue >= $filterValue,
                    '<' => $itemValue < $filterValue,
                    '<=' => $itemValue <= $filterValue,
                    'contains' => is_string($itemValue) && str_contains($itemValue, (string) $filterValue),
                    default => $itemValue == $filterValue,
                };
            }));
        }

        return ['value' => $result, 'count' => count($result)];
    }

    protected function map(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $mapKey = $config['map_key'] ?? null;
        $mapOperation = $config['map_operation'] ?? null;

        if ($mapKey) {
            $result = array_map(fn ($item) => is_array($item) ? ($item[$mapKey] ?? null) : $item, $arr);
        } elseif ($mapOperation) {
            $result = match ($mapOperation) {
                'to_string' => array_map('strval', $arr),
                'to_int' => array_map('intval', $arr),
                'to_float' => array_map('floatval', $arr),
                'trim' => array_map('trim', $arr),
                'uppercase' => array_map('mb_strtoupper', $arr),
                'lowercase' => array_map('mb_strtolower', $arr),
                default => $arr,
            };
        } else {
            $result = $arr;
        }

        return ['value' => $result, 'count' => count($result)];
    }

    protected function merge(array $config, WorkflowContext $context): array
    {
        $arr1 = $this->resolveArray($config, $context, 'array');
        $arr2 = $this->resolveArray($config, $context, 'array_b');

        $result = array_merge($arr1, $arr2);

        return ['value' => $result, 'count' => count($result)];
    }

    protected function count(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);

        return ['value' => count($arr)];
    }

    protected function unique(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $result = array_values(array_unique($arr));

        return ['value' => $result, 'count' => count($result)];
    }

    protected function sort(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $direction = $config['direction'] ?? 'asc';
        $sortKey = $config['sort_key'] ?? null;

        if ($sortKey) {
            usort($arr, function ($a, $b) use ($sortKey, $direction) {
                $aVal = is_array($a) ? ($a[$sortKey] ?? null) : $a;
                $bVal = is_array($b) ? ($b[$sortKey] ?? null) : $b;

                return $direction === 'desc' ? ($bVal <=> $aVal) : ($aVal <=> $bVal);
            });
        } else {
            $direction === 'desc' ? rsort($arr) : sort($arr);
        }

        return ['value' => $arr, 'count' => count($arr)];
    }

    protected function reverse(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);

        return ['value' => array_reverse($arr), 'count' => count($arr)];
    }

    protected function flatten(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $depth = (int) ($config['depth'] ?? PHP_INT_MAX);
        $result = Arr::flatten($arr, $depth);

        return ['value' => $result, 'count' => count($result)];
    }

    protected function pluck(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $pluckKey = $config['pluck_key'] ?? throw new \InvalidArgumentException('pluck_key is required');
        $result = Arr::pluck($arr, $pluckKey);

        return ['value' => $result, 'count' => count($result)];
    }

    protected function slice(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $offset = (int) ($config['offset'] ?? 0);
        $length = isset($config['length']) ? (int) $config['length'] : null;

        $result = array_slice($arr, $offset, $length);

        return ['value' => $result, 'count' => count($result)];
    }

    protected function chunk(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $size = (int) ($config['size'] ?? 1);

        if ($size < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1');
        }

        $result = array_chunk($arr, $size);

        return ['value' => $result, 'chunk_count' => count($result)];
    }

    protected function first(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);

        return ['value' => !empty($arr) ? reset($arr) : null];
    }

    protected function last(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);

        return ['value' => !empty($arr) ? end($arr) : null];
    }

    protected function join(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $delimiter = $config['delimiter'] ?? ', ';

        return ['value' => implode($delimiter, $arr)];
    }

    protected function contains(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $item = $config['item'] ?? null;

        return ['value' => in_array($item, $arr)];
    }

    protected function indexOf(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $item = $config['item'] ?? null;
        $index = array_search($item, $arr);

        return ['value' => $index !== false ? $index : -1];
    }

    protected function keys(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);

        return ['value' => array_keys($arr)];
    }

    protected function values(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);

        return ['value' => array_values($arr)];
    }

    protected function getFromArray(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $key = $config['key'] ?? throw new \InvalidArgumentException('key is required');

        return ['value' => Arr::get($arr, $key)];
    }

    protected function setInArray(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $key = $config['key'] ?? throw new \InvalidArgumentException('key is required');
        $item = $config['item'] ?? null;

        Arr::set($arr, $key, $item);

        return ['value' => $arr];
    }

    protected function removeFromArray(array $config, WorkflowContext $context): array
    {
        $arr = $this->resolveArray($config, $context);
        $key = $config['key'] ?? null;
        $item = $config['item'] ?? null;

        if ($key !== null) {
            Arr::forget($arr, $key);
        } elseif ($item !== null) {
            $arr = array_values(array_filter($arr, fn ($v) => $v !== $item));
        }

        return ['value' => $arr, 'count' => count($arr)];
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
                    'description' => 'The array operation to perform',
                    'enum' => [
                        'push', 'pop', 'shift', 'unshift', 'filter', 'map', 'merge',
                        'count', 'unique', 'sort', 'reverse', 'flatten', 'pluck',
                        'slice', 'chunk', 'first', 'last', 'join', 'contains',
                        'index_of', 'keys', 'values', 'get', 'set', 'remove',
                    ],
                    'required' => true,
                ],
                'array' => [
                    'type' => ['array', 'string'],
                    'description' => 'The array to operate on. Use {{context.key}} for context arrays.',
                    'required' => true,
                ],
                'item' => [
                    'type' => 'mixed',
                    'description' => 'Item for push/unshift/contains/remove operations',
                ],
                'key' => [
                    'type' => 'string',
                    'description' => 'Key for get/set/remove operations (dot notation supported)',
                ],
                'filter_key' => [
                    'type' => 'string',
                    'description' => 'Object key to filter by',
                ],
                'filter_value' => [
                    'type' => 'mixed',
                    'description' => 'Value to filter against',
                ],
                'operator' => [
                    'type' => 'string',
                    'description' => 'Comparison operator for filter',
                    'enum' => ['==', '===', '!=', '>', '>=', '<', '<=', 'contains'],
                ],
                'sort_key' => [
                    'type' => 'string',
                    'description' => 'Object key to sort by',
                ],
                'direction' => [
                    'type' => 'string',
                    'description' => 'Sort direction',
                    'enum' => ['asc', 'desc'],
                    'default' => 'asc',
                ],
                'delimiter' => [
                    'type' => 'string',
                    'description' => 'Delimiter for join operations',
                    'default' => ', ',
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
            'value' => 'The result of the array operation',
            'count' => 'The count of items in the resulting array',
        ];
    }
}
