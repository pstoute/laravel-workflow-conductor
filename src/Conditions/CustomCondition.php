<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Conditions;

use Pstoute\LaravelWorkflows\Data\WorkflowContext;

class CustomCondition extends AbstractCondition
{
    /**
     * @var array<string, callable>
     */
    protected static array $callbacks = [];

    public function getIdentifier(): string
    {
        return 'custom';
    }

    public function getName(): string
    {
        return 'Custom Condition';
    }

    public function getDescription(): string
    {
        return 'Evaluate a custom callback function';
    }

    /**
     * @return array<string, string>
     */
    public function getOperators(): array
    {
        return [
            'callback' => 'Custom Callback',
        ];
    }

    /**
     * Register a custom callback.
     */
    public static function register(string $name, callable $callback): void
    {
        static::$callbacks[$name] = $callback;
    }

    /**
     * Get all registered callbacks.
     *
     * @return array<string, callable>
     */
    public static function getCallbacks(): array
    {
        return static::$callbacks;
    }

    /**
     * Check if a callback is registered.
     */
    public static function hasCallback(string $name): bool
    {
        return isset(static::$callbacks[$name]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function evaluate(WorkflowContext $context, array $config): bool
    {
        $callbackName = $config['callback'] ?? null;
        $params = $config['params'] ?? [];

        if ($callbackName === null) {
            return false;
        }

        // Check for registered callback
        if (static::hasCallback($callbackName)) {
            $callback = static::$callbacks[$callbackName];

            return (bool) $callback($context, $params);
        }

        // Check for class@method format
        if (str_contains($callbackName, '@')) {
            [$class, $method] = explode('@', $callbackName, 2);

            if (class_exists($class) && method_exists($class, $method)) {
                $instance = app($class);

                return (bool) $instance->{$method}($context, $params);
            }
        }

        // Check for invokable class
        if (class_exists($callbackName)) {
            $instance = app($callbackName);

            if (method_exists($instance, '__invoke')) {
                return (bool) $instance($context, $params);
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'callback' => [
                    'type' => 'string',
                    'description' => 'The callback name, class@method, or invokable class',
                    'required' => true,
                ],
                'params' => [
                    'type' => 'object',
                    'description' => 'Additional parameters to pass to the callback',
                ],
            ],
        ];
    }
}
