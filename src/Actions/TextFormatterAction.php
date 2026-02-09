<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Illuminate\Support\Str;
use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class TextFormatterAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'text_formatter';
    }

    public function getName(): string
    {
        return 'Text Formatter';
    }

    public function getDescription(): string
    {
        return 'Transform and format text strings';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $operation = $config['operation'] ?? null;

        if ($operation === null) {
            return ActionResult::failure('No text operation specified');
        }

        try {
            $value = $this->resolveValue($config, $context);

            $result = match ($operation) {
                'uppercase' => mb_strtoupper($value),
                'lowercase' => mb_strtolower($value),
                'title_case' => Str::title($value),
                'camel_case' => Str::camel($value),
                'snake_case' => Str::snake($value),
                'kebab_case' => Str::kebab($value),
                'studly_case' => Str::studly($value),
                'slug' => Str::slug($value, $config['separator'] ?? '-'),
                'trim' => trim($value, $config['characters'] ?? " \t\n\r\0\x0B"),
                'ltrim' => ltrim($value, $config['characters'] ?? " \t\n\r\0\x0B"),
                'rtrim' => rtrim($value, $config['characters'] ?? " \t\n\r\0\x0B"),
                'replace' => str_replace($config['search'] ?? '', $config['replacement'] ?? '', $value),
                'regex_replace' => preg_replace($config['pattern'] ?? '//', $config['replacement'] ?? '', $value),
                'truncate' => Str::limit($value, (int) ($config['length'] ?? 100), $config['end'] ?? '...'),
                'pad_left' => str_pad($value, (int) ($config['length'] ?? 0), $config['pad_string'] ?? ' ', STR_PAD_LEFT),
                'pad_right' => str_pad($value, (int) ($config['length'] ?? 0), $config['pad_string'] ?? ' ', STR_PAD_RIGHT),
                'reverse' => strrev($value),
                'repeat' => str_repeat($value, (int) ($config['times'] ?? 1)),
                'word_count' => str_word_count($value),
                'char_count' => mb_strlen($value),
                'contains' => str_contains($value, $config['search'] ?? ''),
                'starts_with' => str_starts_with($value, $config['prefix'] ?? ''),
                'ends_with' => str_ends_with($value, $config['suffix'] ?? ''),
                'split' => explode($config['delimiter'] ?? ',', $value),
                'join' => is_array($value) ? implode($config['delimiter'] ?? ',', $value) : $value,
                'substring' => mb_substr($value, (int) ($config['start'] ?? 0), isset($config['length']) ? (int) $config['length'] : null),
                'strip_tags' => strip_tags($value, $config['allowed_tags'] ?? ''),
                'nl2br' => nl2br($value),
                'excerpt' => Str::excerpt($value, $config['phrase'] ?? '', ['radius' => (int) ($config['radius'] ?? 100)]),
                'mask' => Str::mask($value, $config['mask_char'] ?? '*', (int) ($config['start'] ?? 0), $config['length'] ?? null),
                'wrap' => ($config['before'] ?? '') . $value . ($config['after'] ?? ''),
                default => null,
            };

            if ($result === null) {
                return ActionResult::failure("Unknown text operation: {$operation}");
            }

            $outputKey = $config['output_key'] ?? null;
            if ($outputKey) {
                $context->set($outputKey, $result);
            }

            return ActionResult::success("Text operation '{$operation}' completed", [
                'result' => $result,
                'operation' => $operation,
            ]);
        } catch (\Throwable $e) {
            return ActionResult::failure("Text operation failed: {$e->getMessage()}", $e);
        }
    }

    protected function resolveValue(array $config, WorkflowContext $context): mixed
    {
        $value = $config['value'] ?? '';

        if (is_string($value) && str_starts_with($value, '{{') && str_ends_with($value, '}}')) {
            $contextKey = trim($value, '{} ');
            $resolved = $context->get($contextKey);

            return $resolved ?? '';
        }

        return $value;
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
                    'description' => 'The text operation to perform',
                    'enum' => [
                        'uppercase', 'lowercase', 'title_case', 'camel_case', 'snake_case',
                        'kebab_case', 'studly_case', 'slug', 'trim', 'ltrim', 'rtrim',
                        'replace', 'regex_replace', 'truncate', 'pad_left', 'pad_right',
                        'reverse', 'repeat', 'word_count', 'char_count', 'contains',
                        'starts_with', 'ends_with', 'split', 'join', 'substring',
                        'strip_tags', 'nl2br', 'excerpt', 'mask', 'wrap',
                    ],
                    'required' => true,
                ],
                'value' => [
                    'type' => ['string', 'array'],
                    'description' => 'The text to transform. Use {{context.key}} for context values.',
                    'required' => true,
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Search string for replace/contains operations',
                ],
                'replacement' => [
                    'type' => 'string',
                    'description' => 'Replacement string for replace operations',
                ],
                'pattern' => [
                    'type' => 'string',
                    'description' => 'Regex pattern for regex_replace',
                ],
                'length' => [
                    'type' => 'integer',
                    'description' => 'Length for truncate, pad, substring operations',
                ],
                'delimiter' => [
                    'type' => 'string',
                    'description' => 'Delimiter for split/join operations',
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
            'result' => 'The transformed text',
            'operation' => 'The operation that was performed',
        ];
    }
}
