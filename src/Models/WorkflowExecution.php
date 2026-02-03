<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workflow_id
 * @property string $trigger_type
 * @property array<string, mixed>|null $trigger_data
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $result
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workflow $workflow
 * @property-read Collection<int, WorkflowExecutionLog> $logs
 */
class WorkflowExecution extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'trigger_data' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('workflow-conductor.database.table_prefix', 'workflow_') . 'executions';
    }

    public function getConnectionName(): ?string
    {
        return config('workflow-conductor.database.connection') ?? parent::getConnectionName();
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return HasMany<WorkflowExecutionLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WorkflowExecutionLog::class, 'execution_id')->orderBy('id');
    }

    /**
     * Mark the execution as started.
     */
    public function markAsStarted(): static
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark the execution as completed.
     *
     * @param array<string, mixed>|null $result
     */
    public function markAsCompleted(?array $result = null): static
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'result' => $result,
        ]);

        return $this;
    }

    /**
     * Mark the execution as failed.
     *
     * @param array<string, mixed>|null $result
     */
    public function markAsFailed(string $error, ?array $result = null): static
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'error' => $error,
            'result' => $result,
        ]);

        return $this;
    }

    /**
     * Mark the execution as skipped.
     */
    public function markAsSkipped(string $reason): static
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'completed_at' => now(),
            'error' => $reason,
        ]);

        return $this;
    }

    /**
     * Check if the execution is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the execution is running.
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Check if the execution is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the execution failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the execution was skipped.
     */
    public function isSkipped(): bool
    {
        return $this->status === self::STATUS_SKIPPED;
    }

    /**
     * Get the execution duration in milliseconds.
     */
    public function getDurationMs(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return (int) $this->started_at->diffInMilliseconds($this->completed_at);
    }

    /**
     * Scope to pending executions.
     *
     * @param \Illuminate\Database\Eloquent\Builder<WorkflowExecution> $query
     * @return \Illuminate\Database\Eloquent\Builder<WorkflowExecution>
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to running executions.
     *
     * @param \Illuminate\Database\Eloquent\Builder<WorkflowExecution> $query
     * @return \Illuminate\Database\Eloquent\Builder<WorkflowExecution>
     */
    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    /**
     * Scope to completed executions.
     *
     * @param \Illuminate\Database\Eloquent\Builder<WorkflowExecution> $query
     * @return \Illuminate\Database\Eloquent\Builder<WorkflowExecution>
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to failed executions.
     *
     * @param \Illuminate\Database\Eloquent\Builder<WorkflowExecution> $query
     * @return \Illuminate\Database\Eloquent\Builder<WorkflowExecution>
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}
