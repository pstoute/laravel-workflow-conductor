<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class SetVariableAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'set_variable';
    }

    public function getName(): string
    {
        return 'Set Variable';
    }

    public function getDescription(): string
    {
        return 'Set a value into the workflow context for use by later actions';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $variables = $config['variables'] ?? [];

        // Support single variable shorthand
        if (empty($variables) && isset($config['key'])) {
            $variables = [['key' => $config['key'], 'value' => $config['value'] ?? null]];
        }

        if (empty($variables)) {
            return ActionResult::failure('No variables specified. Provide "variables" array or "key"/"value" pair.');
        }

        $set = [];

        foreach ($variables as $variable) {
            $key = $variable['key'] ?? null;

            if ($key === null) {
                return ActionResult::failure('Variable is missing "key"');
            }

            $value = $variable['value'] ?? null;

            // Resolve value from context if it's a context reference
            if (is_string($value) && str_starts_with($value, '{{') && str_ends_with($value, '}}')) {
                $contextKey = trim($value, '{} ');
                $value = $context->get($contextKey);
            }

            // Support type casting
            $castTo = $variable['cast'] ?? null;
            if ($castTo !== null && $value !== null) {
                $value = match ($castTo) {
                    'string' => (string) $value,
                    'int', 'integer' => (int) $value,
                    'float', 'double' => (float) $value,
                    'bool', 'boolean' => (bool) $value,
                    'array' => (array) $value,
                    'json' => json_decode((string) $value, true),
                    default => $value,
                };
            }

            $context->set($key, $value);
            $set[$key] = $value;
        }

        return ActionResult::success(
            count($set) . ' variable(s) set in workflow context',
            ['variables' => $set]
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
                'key' => [
                    'type' => 'string',
                    'description' => 'Context key to set (shorthand for single variable)',
                ],
                'value' => [
                    'type' => 'mixed',
                    'description' => 'Value to set. Use {{context.key}} for context references.',
                ],
                'variables' => [
                    'type' => 'array',
                    'description' => 'Array of variables to set: [{key, value, cast?}]',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => [
                                'type' => 'string',
                                'description' => 'Context key (dot notation)',
                                'required' => true,
                            ],
                            'value' => [
                                'type' => 'mixed',
                                'description' => 'Value to set. Use {{context.key}} for context references.',
                            ],
                            'cast' => [
                                'type' => 'string',
                                'description' => 'Cast value to type',
                                'enum' => ['string', 'int', 'float', 'bool', 'array', 'json'],
                            ],
                        ],
                    ],
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
            'variables' => 'Map of context keys to the values that were set',
        ];
    }
}
