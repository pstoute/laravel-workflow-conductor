<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Actions;

use Pstoute\LaravelWorkflows\Data\ActionResult;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

class CustomAction extends AbstractAction
{
    /**
     * @var array<string, callable>
     */
    protected static array $handlers = [];

    public function getIdentifier(): string
    {
        return 'custom';
    }

    public function getName(): string
    {
        return 'Custom Action';
    }

    public function getDescription(): string
    {
        return 'Execute a custom action handler';
    }

    /**
     * Register a custom action handler.
     */
    public static function register(string $name, callable $handler): void
    {
        static::$handlers[$name] = $handler;
    }

    /**
     * Get all registered handlers.
     *
     * @return array<string, callable>
     */
    public static function getHandlers(): array
    {
        return static::$handlers;
    }

    /**
     * Check if a handler is registered.
     */
    public static function hasHandler(string $name): bool
    {
        return isset(static::$handlers[$name]);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $handlerName = $config['handler'] ?? null;
        $params = $config['params'] ?? [];

        if ($handlerName === null) {
            return ActionResult::failure('No handler specified');
        }

        try {
            // Check for registered handler
            if (static::hasHandler($handlerName)) {
                $handler = static::$handlers[$handlerName];
                $result = $handler($context, $params);

                return $this->normalizeResult($result);
            }

            // Check for class@method format
            if (str_contains($handlerName, '@')) {
                [$class, $method] = explode('@', $handlerName, 2);

                if (class_exists($class) && method_exists($class, $method)) {
                    $instance = app($class);
                    $result = $instance->{$method}($context, $params);

                    return $this->normalizeResult($result);
                }

                return ActionResult::failure("Handler method '{$handlerName}' not found");
            }

            // Check for invokable class
            if (class_exists($handlerName)) {
                $instance = app($handlerName);

                if (method_exists($instance, '__invoke')) {
                    $result = $instance($context, $params);

                    return $this->normalizeResult($result);
                }

                // Check if it implements ActionInterface
                if ($instance instanceof \Pstoute\LaravelWorkflows\Contracts\ActionInterface) {
                    return $instance->execute($context, $params);
                }

                return ActionResult::failure("Handler class '{$handlerName}' is not invokable");
            }

            return ActionResult::failure("Handler '{$handlerName}' not found");

        } catch (\Throwable $e) {
            return ActionResult::failure('Custom action failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Normalize the result from a custom handler.
     */
    protected function normalizeResult(mixed $result): ActionResult
    {
        if ($result instanceof ActionResult) {
            return $result;
        }

        if (is_bool($result)) {
            return $result
                ? ActionResult::success('Custom action completed')
                : ActionResult::failure('Custom action returned false');
        }

        if (is_array($result)) {
            return ActionResult::success('Custom action completed', $result);
        }

        return ActionResult::success('Custom action completed', ['result' => $result]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'handler' => [
                    'type' => 'string',
                    'description' => 'Handler name, class@method, or invokable class',
                    'required' => true,
                ],
                'params' => [
                    'type' => 'object',
                    'description' => 'Parameters to pass to the handler',
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
            'result' => 'The result returned by the custom handler',
        ];
    }
}
