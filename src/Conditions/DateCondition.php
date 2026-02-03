<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Conditions;

use Carbon\Carbon;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

class DateCondition extends AbstractCondition
{
    public function getIdentifier(): string
    {
        return 'date';
    }

    public function getName(): string
    {
        return 'Date Condition';
    }

    public function getDescription(): string
    {
        return 'Compare date/time values';
    }

    /**
     * @return array<string, string>
     */
    public function getOperators(): array
    {
        return [
            'equals' => 'Equals (same day)',
            'before' => 'Before',
            'after' => 'After',
            'on_or_before' => 'On or Before',
            'on_or_after' => 'On or After',
            'between' => 'Between',
            'days_ago' => 'Days Ago',
            'days_from_now' => 'Days From Now',
            'is_today' => 'Is Today',
            'is_past' => 'Is in the Past',
            'is_future' => 'Is in the Future',
            'same_week' => 'Same Week',
            'same_month' => 'Same Month',
            'same_year' => 'Same Year',
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public function evaluate(WorkflowContext $context, array $config): bool
    {
        $field = $config['field'] ?? null;
        $operator = $config['operator'] ?? 'equals';
        $value = $config['value'] ?? null;

        if ($field === null) {
            return false;
        }

        $fieldValue = $context->get($field);

        if ($fieldValue === null) {
            return false;
        }

        try {
            $date = $this->parseDate($fieldValue);
        } catch (\Exception) {
            return false;
        }

        return match ($operator) {
            'equals' => $this->isSameDay($date, $value),
            'before' => $this->isBefore($date, $value),
            'after' => $this->isAfter($date, $value),
            'on_or_before' => $this->isOnOrBefore($date, $value),
            'on_or_after' => $this->isOnOrAfter($date, $value),
            'between' => $this->isBetween($date, $value),
            'days_ago' => $this->isDaysAgo($date, $value),
            'days_from_now' => $this->isDaysFromNow($date, $value),
            'is_today' => $date->isToday(),
            'is_past' => $date->isPast(),
            'is_future' => $date->isFuture(),
            'same_week' => $this->isSameWeek($date, $value),
            'same_month' => $this->isSameMonth($date, $value),
            'same_year' => $this->isSameYear($date, $value),
            default => false,
        };
    }

    protected function parseDate(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse($value);
    }

    protected function isSameDay(Carbon $date, mixed $compareValue): bool
    {
        $compareDate = $this->parseDate($compareValue ?? 'now');

        return $date->isSameDay($compareDate);
    }

    protected function isBefore(Carbon $date, mixed $compareValue): bool
    {
        $compareDate = $this->parseDate($compareValue ?? 'now');

        return $date->isBefore($compareDate);
    }

    protected function isAfter(Carbon $date, mixed $compareValue): bool
    {
        $compareDate = $this->parseDate($compareValue ?? 'now');

        return $date->isAfter($compareDate);
    }

    protected function isOnOrBefore(Carbon $date, mixed $compareValue): bool
    {
        $compareDate = $this->parseDate($compareValue ?? 'now');

        return $date->lte($compareDate);
    }

    protected function isOnOrAfter(Carbon $date, mixed $compareValue): bool
    {
        $compareDate = $this->parseDate($compareValue ?? 'now');

        return $date->gte($compareDate);
    }

    protected function isBetween(Carbon $date, mixed $range): bool
    {
        if (! is_array($range) || count($range) !== 2) {
            return false;
        }

        $start = $this->parseDate($range[0]);
        $end = $this->parseDate($range[1]);

        return $date->between($start, $end);
    }

    protected function isDaysAgo(Carbon $date, mixed $days): bool
    {
        if (! is_numeric($days)) {
            return false;
        }

        $targetDate = Carbon::now()->subDays((int) $days);

        return $date->isSameDay($targetDate);
    }

    protected function isDaysFromNow(Carbon $date, mixed $days): bool
    {
        if (! is_numeric($days)) {
            return false;
        }

        $targetDate = Carbon::now()->addDays((int) $days);

        return $date->isSameDay($targetDate);
    }

    protected function isSameWeek(Carbon $date, mixed $compareValue): bool
    {
        $compareDate = $this->parseDate($compareValue ?? 'now');

        return $date->isSameWeek($compareDate);
    }

    protected function isSameMonth(Carbon $date, mixed $compareValue): bool
    {
        $compareDate = $this->parseDate($compareValue ?? 'now');

        return $date->isSameMonth($compareDate);
    }

    protected function isSameYear(Carbon $date, mixed $compareValue): bool
    {
        $compareDate = $this->parseDate($compareValue ?? 'now');

        return $date->isSameYear($compareDate);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'field' => [
                    'type' => 'string',
                    'description' => 'The date field path to evaluate',
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
                    'description' => 'The date value to compare against (can be a date string, timestamp, or array for "between")',
                ],
            ],
        ];
    }
}
