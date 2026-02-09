<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Carbon\Carbon;
use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class DateFormatterAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'date_formatter';
    }

    public function getName(): string
    {
        return 'Date Formatter';
    }

    public function getDescription(): string
    {
        return 'Format dates, add/subtract intervals, and convert timezones';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $operation = $config['operation'] ?? 'format';

        try {
            $result = match ($operation) {
                'format' => $this->formatDate($config, $context),
                'add' => $this->addInterval($config, $context),
                'subtract' => $this->subtractInterval($config, $context),
                'diff' => $this->dateDiff($config, $context),
                'convert_timezone' => $this->convertTimezone($config, $context),
                'now' => $this->now($config),
                'parse' => $this->parseDate($config, $context),
                'start_of' => $this->startOf($config, $context),
                'end_of' => $this->endOf($config, $context),
                'is_before' => $this->isBefore($config, $context),
                'is_after' => $this->isAfter($config, $context),
                'is_between' => $this->isBetween($config, $context),
                default => null,
            };

            if ($result === null) {
                return ActionResult::failure("Unknown date operation: {$operation}");
            }

            $outputKey = $config['output_key'] ?? null;
            if ($outputKey) {
                $context->set($outputKey, $result['value']);
            }

            return ActionResult::success("Date operation '{$operation}' completed", $result);
        } catch (\Throwable $e) {
            return ActionResult::failure("Date operation failed: {$e->getMessage()}", $e);
        }
    }

    protected function resolveDate(array $config, WorkflowContext $context, string $key = 'date'): Carbon
    {
        $value = $config[$key] ?? 'now';

        if (is_string($value) && str_starts_with($value, '{{') && str_ends_with($value, '}}')) {
            $contextKey = trim($value, '{} ');
            $value = $context->get($contextKey);
        }

        if ($value === 'now') {
            return Carbon::now($config['timezone'] ?? null);
        }

        return Carbon::parse($value, $config['timezone'] ?? null);
    }

    protected function formatDate(array $config, WorkflowContext $context): array
    {
        $date = $this->resolveDate($config, $context);
        $format = $config['format'] ?? 'Y-m-d H:i:s';

        $formatted = $date->format($format);

        return [
            'value' => $formatted,
            'iso8601' => $date->toIso8601String(),
            'timestamp' => $date->timestamp,
        ];
    }

    protected function addInterval(array $config, WorkflowContext $context): array
    {
        $date = $this->resolveDate($config, $context);
        $amount = (int) ($config['amount'] ?? 1);
        $unit = $config['unit'] ?? 'days';

        $result = match ($unit) {
            'seconds' => $date->addSeconds($amount),
            'minutes' => $date->addMinutes($amount),
            'hours' => $date->addHours($amount),
            'days' => $date->addDays($amount),
            'weeks' => $date->addWeeks($amount),
            'months' => $date->addMonths($amount),
            'years' => $date->addYears($amount),
            default => throw new \InvalidArgumentException("Unknown time unit: {$unit}"),
        };

        $format = $config['format'] ?? 'Y-m-d H:i:s';

        return [
            'value' => $result->format($format),
            'iso8601' => $result->toIso8601String(),
            'timestamp' => $result->timestamp,
        ];
    }

    protected function subtractInterval(array $config, WorkflowContext $context): array
    {
        $date = $this->resolveDate($config, $context);
        $amount = (int) ($config['amount'] ?? 1);
        $unit = $config['unit'] ?? 'days';

        $result = match ($unit) {
            'seconds' => $date->subSeconds($amount),
            'minutes' => $date->subMinutes($amount),
            'hours' => $date->subHours($amount),
            'days' => $date->subDays($amount),
            'weeks' => $date->subWeeks($amount),
            'months' => $date->subMonths($amount),
            'years' => $date->subYears($amount),
            default => throw new \InvalidArgumentException("Unknown time unit: {$unit}"),
        };

        $format = $config['format'] ?? 'Y-m-d H:i:s';

        return [
            'value' => $result->format($format),
            'iso8601' => $result->toIso8601String(),
            'timestamp' => $result->timestamp,
        ];
    }

    protected function dateDiff(array $config, WorkflowContext $context): array
    {
        $dateA = $this->resolveDate($config, $context, 'date_a');
        $dateB = $this->resolveDate($config, $context, 'date_b');
        $unit = $config['unit'] ?? 'days';

        $diff = match ($unit) {
            'seconds' => $dateA->diffInSeconds($dateB),
            'minutes' => $dateA->diffInMinutes($dateB),
            'hours' => $dateA->diffInHours($dateB),
            'days' => $dateA->diffInDays($dateB),
            'weeks' => $dateA->diffInWeeks($dateB),
            'months' => $dateA->diffInMonths($dateB),
            'years' => $dateA->diffInYears($dateB),
            default => throw new \InvalidArgumentException("Unknown time unit: {$unit}"),
        };

        return [
            'value' => $diff,
            'unit' => $unit,
            'human' => $dateA->diffForHumans($dateB),
        ];
    }

    protected function convertTimezone(array $config, WorkflowContext $context): array
    {
        $date = $this->resolveDate($config, $context);
        $toTimezone = $config['to_timezone'] ?? throw new \InvalidArgumentException('to_timezone is required');
        $format = $config['format'] ?? 'Y-m-d H:i:s';

        $converted = $date->setTimezone($toTimezone);

        return [
            'value' => $converted->format($format),
            'iso8601' => $converted->toIso8601String(),
            'timezone' => $toTimezone,
            'timestamp' => $converted->timestamp,
        ];
    }

    protected function now(array $config): array
    {
        $timezone = $config['timezone'] ?? null;
        $format = $config['format'] ?? 'Y-m-d H:i:s';
        $now = Carbon::now($timezone);

        return [
            'value' => $now->format($format),
            'iso8601' => $now->toIso8601String(),
            'timestamp' => $now->timestamp,
        ];
    }

    protected function parseDate(array $config, WorkflowContext $context): array
    {
        $date = $this->resolveDate($config, $context);
        $format = $config['format'] ?? 'Y-m-d H:i:s';

        return [
            'value' => $date->format($format),
            'iso8601' => $date->toIso8601String(),
            'timestamp' => $date->timestamp,
            'year' => $date->year,
            'month' => $date->month,
            'day' => $date->day,
            'hour' => $date->hour,
            'minute' => $date->minute,
            'day_of_week' => $date->dayOfWeek,
            'day_name' => $date->dayName,
        ];
    }

    protected function startOf(array $config, WorkflowContext $context): array
    {
        $date = $this->resolveDate($config, $context);
        $unit = $config['unit'] ?? 'day';
        $format = $config['format'] ?? 'Y-m-d H:i:s';

        $result = match ($unit) {
            'minute' => $date->startOfMinute(),
            'hour' => $date->startOfHour(),
            'day' => $date->startOfDay(),
            'week' => $date->startOfWeek(),
            'month' => $date->startOfMonth(),
            'quarter' => $date->startOfQuarter(),
            'year' => $date->startOfYear(),
            default => throw new \InvalidArgumentException("Unknown unit: {$unit}"),
        };

        return [
            'value' => $result->format($format),
            'iso8601' => $result->toIso8601String(),
            'timestamp' => $result->timestamp,
        ];
    }

    protected function endOf(array $config, WorkflowContext $context): array
    {
        $date = $this->resolveDate($config, $context);
        $unit = $config['unit'] ?? 'day';
        $format = $config['format'] ?? 'Y-m-d H:i:s';

        $result = match ($unit) {
            'minute' => $date->endOfMinute(),
            'hour' => $date->endOfHour(),
            'day' => $date->endOfDay(),
            'week' => $date->endOfWeek(),
            'month' => $date->endOfMonth(),
            'quarter' => $date->endOfQuarter(),
            'year' => $date->endOfYear(),
            default => throw new \InvalidArgumentException("Unknown unit: {$unit}"),
        };

        return [
            'value' => $result->format($format),
            'iso8601' => $result->toIso8601String(),
            'timestamp' => $result->timestamp,
        ];
    }

    protected function isBefore(array $config, WorkflowContext $context): array
    {
        $dateA = $this->resolveDate($config, $context, 'date_a');
        $dateB = $this->resolveDate($config, $context, 'date_b');

        return ['value' => $dateA->isBefore($dateB)];
    }

    protected function isAfter(array $config, WorkflowContext $context): array
    {
        $dateA = $this->resolveDate($config, $context, 'date_a');
        $dateB = $this->resolveDate($config, $context, 'date_b');

        return ['value' => $dateA->isAfter($dateB)];
    }

    protected function isBetween(array $config, WorkflowContext $context): array
    {
        $date = $this->resolveDate($config, $context);
        $start = $this->resolveDate($config, $context, 'start_date');
        $end = $this->resolveDate($config, $context, 'end_date');

        return ['value' => $date->isBetween($start, $end)];
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
                    'description' => 'The date operation to perform',
                    'enum' => ['format', 'add', 'subtract', 'diff', 'convert_timezone', 'now', 'parse', 'start_of', 'end_of', 'is_before', 'is_after', 'is_between'],
                    'required' => true,
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'The date to operate on. Use {{context.key}} for context values, or "now".',
                ],
                'format' => [
                    'type' => 'string',
                    'description' => 'PHP date format string (e.g., Y-m-d H:i:s)',
                    'default' => 'Y-m-d H:i:s',
                ],
                'amount' => [
                    'type' => 'integer',
                    'description' => 'Amount for add/subtract operations',
                ],
                'unit' => [
                    'type' => 'string',
                    'description' => 'Time unit for interval and diff operations',
                    'enum' => ['seconds', 'minutes', 'hours', 'days', 'weeks', 'months', 'years'],
                ],
                'timezone' => [
                    'type' => 'string',
                    'description' => 'Source timezone for date parsing',
                ],
                'to_timezone' => [
                    'type' => 'string',
                    'description' => 'Target timezone for conversion',
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
            'value' => 'The formatted date or operation result',
            'iso8601' => 'ISO 8601 formatted date string',
            'timestamp' => 'Unix timestamp',
        ];
    }
}
