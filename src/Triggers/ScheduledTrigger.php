<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Triggers;

use Cron\CronExpression;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

class ScheduledTrigger extends AbstractTrigger
{
    public function getIdentifier(): string
    {
        return 'scheduled';
    }

    public function getName(): string
    {
        return 'Scheduled';
    }

    public function getDescription(): string
    {
        return 'Triggered on a schedule (cron expression)';
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cron' => [
                    'type' => 'string',
                    'description' => 'Cron expression (e.g., "0 9 * * *" for 9 AM daily)',
                    'required' => true,
                ],
                'timezone' => [
                    'type' => 'string',
                    'description' => 'Timezone for the schedule (default: UTC)',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $triggerConfig
     */
    public function shouldTrigger(WorkflowContext $context, array $triggerConfig): bool
    {
        $cronExpression = $triggerConfig['cron'] ?? null;

        if ($cronExpression === null) {
            return false;
        }

        $timezone = $triggerConfig['timezone'] ?? config('app.timezone', 'UTC');

        try {
            $cron = new CronExpression($cronExpression);

            return $cron->isDue('now', $timezone);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if a cron expression is valid.
     */
    public static function isValidCron(string $expression): bool
    {
        try {
            new CronExpression($expression);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get the next run time for a cron expression.
     */
    public static function getNextRunTime(string $expression, ?string $timezone = null): ?\DateTimeInterface
    {
        try {
            $cron = new CronExpression($expression);
            $timezone = $timezone ?? config('app.timezone', 'UTC');

            return $cron->getNextRunDate('now', 0, false, $timezone);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableData(): array
    {
        return [
            'scheduled_at' => 'The scheduled execution time',
            'cron_expression' => 'The cron expression that triggered this',
        ];
    }
}
