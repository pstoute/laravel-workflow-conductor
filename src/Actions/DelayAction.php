<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Actions;

use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class DelayAction extends AbstractAction
{
    public function getIdentifier(): string
    {
        return 'delay';
    }

    public function getName(): string
    {
        return 'Delay';
    }

    public function getDescription(): string
    {
        return 'Add a delay before subsequent actions';
    }

    public function supportsAsync(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $duration = $config['duration'] ?? 0;
        $unit = $config['unit'] ?? 'seconds';

        // Convert to seconds
        $seconds = match ($unit) {
            'minutes' => $duration * 60,
            'hours' => $duration * 3600,
            'days' => $duration * 86400,
            default => $duration,
        };

        // Check max delay
        $maxDelay = config('workflow-conductor.actions.delay.max_delay', 86400 * 30);
        if ($seconds > $maxDelay) {
            return ActionResult::failure("Delay of {$seconds} seconds exceeds maximum allowed delay of {$maxDelay} seconds");
        }

        if ($seconds < 0) {
            return ActionResult::failure('Delay cannot be negative');
        }

        // For synchronous execution, we could sleep (not recommended for long delays)
        // But typically delays are handled by the workflow engine scheduling delayed jobs
        // This action just returns the delay information

        return ActionResult::success("Delay of {$seconds} seconds configured", [
            'delay_seconds' => $seconds,
            'delay_until' => now()->addSeconds($seconds)->toIso8601String(),
            'original_duration' => $duration,
            'original_unit' => $unit,
        ]);
    }

    /**
     * Parse a delay string like "2 hours" or "30 minutes".
     *
     * @return array{duration: int, unit: string}|null
     */
    public static function parseDelayString(string $delay): ?array
    {
        $pattern = '/^(\d+)\s*(seconds?|minutes?|hours?|days?)?$/i';

        if (preg_match($pattern, trim($delay), $matches)) {
            $duration = (int) $matches[1];
            $unit = isset($matches[2]) ? strtolower($matches[2]) : 'seconds';

            // Normalize unit to singular
            $unit = rtrim($unit, 's');

            return [
                'duration' => $duration,
                'unit' => $unit . 's', // Add back the 's' for consistency
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'duration' => [
                    'type' => 'integer',
                    'description' => 'Duration of the delay',
                    'required' => true,
                ],
                'unit' => [
                    'type' => 'string',
                    'description' => 'Time unit for the delay',
                    'enum' => ['seconds', 'minutes', 'hours', 'days'],
                    'default' => 'seconds',
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
            'delay_seconds' => 'The delay in seconds',
            'delay_until' => 'ISO 8601 timestamp when the delay will end',
        ];
    }
}
