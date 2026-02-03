<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $execution_id
 * @property int|null $action_id
 * @property string $type
 * @property string $status
 * @property array<string, mixed>|null $input
 * @property array<string, mixed>|null $output
 * @property string|null $error
 * @property int|null $duration_ms
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read WorkflowExecution $execution
 * @property-read WorkflowAction|null $action
 */
class WorkflowExecutionLog extends Model
{
    public const TYPE_TRIGGER = 'trigger';
    public const TYPE_CONDITION = 'condition';
    public const TYPE_ACTION = 'action';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'duration_ms' => 'integer',
    ];

    public function getTable(): string
    {
        return config('workflow-conductor.database.table_prefix', 'workflow_') . 'execution_logs';
    }

    public function getConnectionName(): ?string
    {
        return config('workflow-conductor.database.connection') ?? parent::getConnectionName();
    }

    /**
     * @return BelongsTo<WorkflowExecution, $this>
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(WorkflowExecution::class, 'execution_id');
    }

    /**
     * @return BelongsTo<WorkflowAction, $this>
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(WorkflowAction::class, 'action_id');
    }

    /**
     * Check if the log entry was successful.
     */
    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Check if the log entry failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the log entry was skipped.
     */
    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    /**
     * Create a trigger log entry.
     *
     * @param array<string, mixed>|null $input
     * @param array<string, mixed>|null $output
     */
    public static function createTriggerLog(
        WorkflowExecution $execution,
        string $status,
        ?array $input = null,
        ?array $output = null,
        ?string $error = null,
        ?int $durationMs = null
    ): static {
        return static::create([
            'execution_id' => $execution->id,
            'type' => self::TYPE_TRIGGER,
            'status' => $status,
            'input' => $input,
            'output' => $output,
            'error' => $error,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Create a condition log entry.
     *
     * @param array<string, mixed>|null $input
     * @param array<string, mixed>|null $output
     */
    public static function createConditionLog(
        WorkflowExecution $execution,
        string $status,
        ?array $input = null,
        ?array $output = null,
        ?string $error = null,
        ?int $durationMs = null
    ): static {
        return static::create([
            'execution_id' => $execution->id,
            'type' => self::TYPE_CONDITION,
            'status' => $status,
            'input' => $input,
            'output' => $output,
            'error' => $error,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Create an action log entry.
     *
     * @param array<string, mixed>|null $input
     * @param array<string, mixed>|null $output
     */
    public static function createActionLog(
        WorkflowExecution $execution,
        WorkflowAction $action,
        string $status,
        ?array $input = null,
        ?array $output = null,
        ?string $error = null,
        ?int $durationMs = null
    ): static {
        return static::create([
            'execution_id' => $execution->id,
            'action_id' => $action->id,
            'type' => self::TYPE_ACTION,
            'status' => $status,
            'input' => $input,
            'output' => $output,
            'error' => $error,
            'duration_ms' => $durationMs,
        ]);
    }
}
