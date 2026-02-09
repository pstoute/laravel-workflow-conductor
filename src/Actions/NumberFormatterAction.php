<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class NumberFormatterAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'number_formatter';
    }

    public function getName(): string
    {
        return 'Number Formatter';
    }

    public function getDescription(): string
    {
        return 'Format numbers as currency, percentages, decimals, or ordinals';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $format = $config['format'] ?? 'decimal';

        try {
            $value = $config['value'] ?? null;

            if ($value === null) {
                return ActionResult::failure('No value specified');
            }

            // Resolve from context if needed
            if (is_string($value) && str_starts_with($value, '{{') && str_ends_with($value, '}}')) {
                $contextKey = trim($value, '{} ');
                $value = $context->get($contextKey);

                if ($value === null) {
                    return ActionResult::failure("Context key '{$contextKey}' not found");
                }
            }

            if (!is_numeric($value)) {
                return ActionResult::failure("Value is not numeric: {$value}");
            }

            $value = (float) $value;

            $result = match ($format) {
                'currency' => $this->formatCurrency($value, $config),
                'decimal' => $this->formatDecimal($value, $config),
                'percentage' => $this->formatPercentage($value, $config),
                'ordinal' => $this->formatOrdinal((int) $value),
                'compact' => $this->formatCompact($value, $config),
                'words' => $this->formatWords((int) $value),
                'bytes' => $this->formatBytes($value, $config),
                default => null,
            };

            if ($result === null) {
                return ActionResult::failure("Unknown number format: {$format}");
            }

            $outputKey = $config['output_key'] ?? null;
            if ($outputKey) {
                $context->set($outputKey, $result);
            }

            return ActionResult::success("Number formatted as '{$format}'", [
                'result' => $result,
                'original_value' => $value,
                'format' => $format,
            ]);
        } catch (\Throwable $e) {
            return ActionResult::failure("Number formatting failed: {$e->getMessage()}", $e);
        }
    }

    protected function formatCurrency(float $value, array $config): string
    {
        $symbol = $config['currency_symbol'] ?? '$';
        $decimals = (int) ($config['decimals'] ?? 2);
        $thousandsSep = $config['thousands_separator'] ?? ',';
        $decimalPoint = $config['decimal_point'] ?? '.';

        return $symbol . number_format($value, $decimals, $decimalPoint, $thousandsSep);
    }

    protected function formatDecimal(float $value, array $config): string
    {
        $decimals = (int) ($config['decimals'] ?? 2);
        $thousandsSep = $config['thousands_separator'] ?? ',';
        $decimalPoint = $config['decimal_point'] ?? '.';

        return number_format($value, $decimals, $decimalPoint, $thousandsSep);
    }

    protected function formatPercentage(float $value, array $config): string
    {
        $decimals = (int) ($config['decimals'] ?? 1);
        $multiply = $config['multiply_by_100'] ?? true;

        if ($multiply) {
            $value *= 100;
        }

        return number_format($value, $decimals) . '%';
    }

    protected function formatOrdinal(int $value): string
    {
        if (in_array(abs($value) % 100, [11, 12, 13])) {
            return $value . 'th';
        }

        return $value . match (abs($value) % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    protected function formatCompact(float $value, array $config): string
    {
        $decimals = (int) ($config['decimals'] ?? 1);
        $abs = abs($value);
        $sign = $value < 0 ? '-' : '';

        if ($abs >= 1_000_000_000_000) {
            return $sign . number_format($abs / 1_000_000_000_000, $decimals) . 'T';
        }
        if ($abs >= 1_000_000_000) {
            return $sign . number_format($abs / 1_000_000_000, $decimals) . 'B';
        }
        if ($abs >= 1_000_000) {
            return $sign . number_format($abs / 1_000_000, $decimals) . 'M';
        }
        if ($abs >= 1_000) {
            return $sign . number_format($abs / 1_000, $decimals) . 'K';
        }

        return $sign . number_format($abs, $decimals);
    }

    protected function formatWords(int $value): string
    {
        $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
            'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
            'seventeen', 'eighteen', 'nineteen'];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        if ($value < 0) {
            return 'negative ' . $this->formatWords(abs($value));
        }
        if ($value === 0) {
            return 'zero';
        }
        if ($value < 20) {
            return $ones[$value];
        }
        if ($value < 100) {
            return $tens[(int) ($value / 10)] . ($value % 10 ? '-' . $ones[$value % 10] : '');
        }
        if ($value < 1000) {
            return $ones[(int) ($value / 100)] . ' hundred' . ($value % 100 ? ' and ' . $this->formatWords($value % 100) : '');
        }

        return number_format($value); // Fallback for large numbers
    }

    protected function formatBytes(float $value, array $config): string
    {
        $decimals = (int) ($config['decimals'] ?? 2);
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $decimals) . ' ' . $units[$index];
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'value' => [
                    'type' => ['number', 'string'],
                    'description' => 'The number to format. Use {{context.key}} for context values.',
                    'required' => true,
                ],
                'format' => [
                    'type' => 'string',
                    'description' => 'The format to apply',
                    'enum' => ['currency', 'decimal', 'percentage', 'ordinal', 'compact', 'words', 'bytes'],
                    'default' => 'decimal',
                ],
                'decimals' => [
                    'type' => 'integer',
                    'description' => 'Number of decimal places',
                    'default' => 2,
                ],
                'currency_symbol' => [
                    'type' => 'string',
                    'description' => 'Currency symbol (for currency format)',
                    'default' => '$',
                ],
                'thousands_separator' => [
                    'type' => 'string',
                    'description' => 'Thousands separator character',
                    'default' => ',',
                ],
                'decimal_point' => [
                    'type' => 'string',
                    'description' => 'Decimal point character',
                    'default' => '.',
                ],
                'multiply_by_100' => [
                    'type' => 'boolean',
                    'description' => 'Whether to multiply by 100 for percentage (e.g., 0.5 → 50%)',
                    'default' => true,
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
            'result' => 'The formatted number string',
            'original_value' => 'The original numeric value',
            'format' => 'The format that was applied',
        ];
    }
}
