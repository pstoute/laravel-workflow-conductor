<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

class VariableInterpolator
{
    /**
     * Pattern to match {{ variable }} or {{ variable | filter }} or {{ variable | filter:arg }}
     */
    protected const PATTERN = '/\{\{\s*([^}|]+?)(?:\s*\|\s*([^}]+))?\s*\}\}/';

    /**
     * Interpolate variables in a string or array.
     */
    public function interpolate(mixed $value, WorkflowContext $context): mixed
    {
        if (is_string($value)) {
            return $this->interpolateString($value, $context);
        }

        if (is_array($value)) {
            return $this->interpolateArray($value, $context);
        }

        return $value;
    }

    /**
     * Interpolate variables in a string.
     */
    protected function interpolateString(string $value, WorkflowContext $context): mixed
    {
        // Check if the entire string is a single variable reference
        if (preg_match('/^\{\{\s*([^}|]+?)(?:\s*\|\s*([^}]+))?\s*\}\}$/', $value, $match)) {
            $path = trim($match[1]);
            $filter = isset($match[2]) ? trim($match[2]) : null;

            $resolved = $this->resolveValue($path, $context);

            if ($filter !== null) {
                $resolved = $this->applyFilter($resolved, $filter);
            }

            // Return non-string values as-is when it's the entire value
            return $resolved;
        }

        // Otherwise, do string interpolation
        return preg_replace_callback(self::PATTERN, function ($matches) use ($context) {
            $path = trim($matches[1]);
            $filter = isset($matches[2]) ? trim($matches[2]) : null;

            $resolved = $this->resolveValue($path, $context);

            if ($filter !== null) {
                $resolved = $this->applyFilter($resolved, $filter);
            }

            // Convert to string for interpolation within larger strings
            return $this->toString($resolved);
        }, $value);
    }

    /**
     * Interpolate variables in an array recursively.
     *
     * @param array<mixed> $array
     * @return array<mixed>
     */
    protected function interpolateArray(array $array, WorkflowContext $context): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $interpolatedKey = is_string($key) ? $this->interpolateString($key, $context) : $key;
            $result[$interpolatedKey] = $this->interpolate($value, $context);
        }

        return $result;
    }

    /**
     * Resolve a value from the context using dot notation.
     */
    protected function resolveValue(string $path, WorkflowContext $context): mixed
    {
        // Handle config() function
        if (Str::startsWith($path, 'config.')) {
            return config(Str::after($path, 'config.'));
        }

        // Handle env() function
        if (Str::startsWith($path, 'env.')) {
            return env(Str::after($path, 'env.'));
        }

        // Handle now() function
        if ($path === 'now') {
            return now();
        }

        return $context->get($path);
    }

    /**
     * Apply a filter to a value.
     */
    protected function applyFilter(mixed $value, string $filterString): mixed
    {
        // Parse filter name and arguments
        $parts = explode(':', $filterString, 2);
        $filter = trim($parts[0]);
        $args = isset($parts[1]) ? array_map('trim', explode(',', $parts[1])) : [];

        return match ($filter) {
            'uppercase', 'upper' => is_string($value) ? strtoupper($value) : $value,
            'lowercase', 'lower' => is_string($value) ? strtolower($value) : $value,
            'ucfirst', 'capitalize' => is_string($value) ? ucfirst($value) : $value,
            'ucwords', 'title' => is_string($value) ? ucwords($value) : $value,
            'trim' => is_string($value) ? trim($value) : $value,
            'slug' => is_string($value) ? Str::slug($value) : $value,
            'snake' => is_string($value) ? Str::snake($value) : $value,
            'camel' => is_string($value) ? Str::camel($value) : $value,
            'studly', 'pascal' => is_string($value) ? Str::studly($value) : $value,
            'number_format' => $this->numberFormat($value, $args),
            'date' => $this->dateFormat($value, $args),
            'default' => $value ?? ($args[0] ?? ''),
            'json' => json_encode($value),
            'count' => is_countable($value) ? count($value) : 0,
            'first' => is_array($value) ? ($value[0] ?? null) : $value,
            'last' => is_array($value) ? end($value) : $value,
            'join', 'implode' => is_array($value) ? implode(($args[0] ?? '') !== '' ? $args[0] : ', ', $value) : $value,
            'split', 'explode' => is_string($value) ? explode($args[0] ?? ',', $value) : $value,
            'length', 'strlen' => is_string($value) ? strlen($value) : (is_array($value) ? count($value) : 0),
            'substr', 'substring' => $this->substring($value, $args),
            'replace' => $this->replace($value, $args),
            'money', 'currency' => $this->formatMoney($value, $args),
            'bool', 'boolean' => (bool) $value,
            'int', 'integer' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'abs' => is_numeric($value) ? abs($value) : $value,
            'round' => is_numeric($value) ? round((float) $value, (int) ($args[0] ?? 0)) : $value,
            'floor' => is_numeric($value) ? floor((float) $value) : $value,
            'ceil' => is_numeric($value) ? ceil((float) $value) : $value,
            default => $value,
        };
    }

    /**
     * Format a number.
     *
     * @param array<string> $args
     */
    protected function numberFormat(mixed $value, array $args): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }

        $decimals = (int) ($args[0] ?? 0);
        $decimalSeparator = $args[1] ?? '.';
        $thousandsSeparator = $args[2] ?? ',';

        return number_format((float) $value, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    /**
     * Format a date.
     *
     * @param array<string> $args
     */
    protected function dateFormat(mixed $value, array $args): string
    {
        if ($value === null) {
            return '';
        }

        $format = $args[0] ?? 'Y-m-d H:i:s';

        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        if (is_string($value) || is_numeric($value)) {
            try {
                return (new \DateTime((string) $value))->format($format);
            } catch (\Exception) {
                return (string) $value;
            }
        }

        return (string) $value;
    }

    /**
     * Get a substring.
     *
     * @param array<string> $args
     */
    protected function substring(mixed $value, array $args): string
    {
        if (! is_string($value)) {
            return (string) $value;
        }

        $start = (int) ($args[0] ?? 0);
        $length = isset($args[1]) ? (int) $args[1] : null;

        return $length !== null ? substr($value, $start, $length) : substr($value, $start);
    }

    /**
     * Replace string.
     *
     * @param array<string> $args
     */
    protected function replace(mixed $value, array $args): string
    {
        if (! is_string($value) || count($args) < 2) {
            return (string) $value;
        }

        return str_replace($args[0], $args[1], $value);
    }

    /**
     * Format as money/currency.
     *
     * @param array<string> $args
     */
    protected function formatMoney(mixed $value, array $args): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }

        $symbol = $args[0] ?? '$';
        $decimals = (int) ($args[1] ?? 2);

        return $symbol . number_format((float) $value, $decimals);
    }

    /**
     * Convert a value to string.
     */
    protected function toString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            if ($value instanceof Model) {
                return (string) $value->getKey();
            }

            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            return json_encode($value) ?: '';
        }

        return (string) $value;
    }
}
