<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $workflow_id
 * @property string $type
 * @property array<string, mixed> $configuration
 * @property int $order
 * @property int $delay
 * @property bool $continue_on_failure
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Workflow $workflow
 * @property-read \Illuminate\Database\Eloquent\Collection<int, WorkflowExecutionLog> $logs
 */
class WorkflowAction extends Model
{
    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'configuration' => 'array',
        'order' => 'integer',
        'delay' => 'integer',
        'continue_on_failure' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('workflow-conductor.database.table_prefix', 'workflow_') . 'actions';
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
        return $this->hasMany(WorkflowExecutionLog::class, 'action_id');
    }

    /**
     * Get a configuration value.
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return data_get($this->configuration, $key, $default);
    }

    /**
     * Set a configuration value.
     */
    public function setConfig(string $key, mixed $value): static
    {
        $config = $this->configuration ?? [];
        data_set($config, $key, $value);
        $this->configuration = $config;

        return $this;
    }

    /**
     * Check if this action has a delay.
     */
    public function hasDelay(): bool
    {
        return $this->delay > 0;
    }

    /**
     * Check if workflow should continue if this action fails.
     */
    public function shouldContinueOnFailure(): bool
    {
        return $this->continue_on_failure;
    }
}
