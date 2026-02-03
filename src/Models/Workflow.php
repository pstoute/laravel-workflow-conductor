<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property array<string, mixed>|null $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Collection<int, WorkflowTrigger> $triggers
 * @property-read Collection<int, WorkflowCondition> $conditions
 * @property-read Collection<int, WorkflowAction> $actions
 * @property-read Collection<int, WorkflowExecution> $executions
 */
class Workflow extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function getTable(): string
    {
        return config('workflows.database.table_prefix', 'workflow_') . 'workflows';
    }

    public function getConnectionName(): ?string
    {
        return config('workflows.database.connection') ?? parent::getConnectionName();
    }

    /**
     * @return HasMany<WorkflowTrigger, $this>
     */
    public function triggers(): HasMany
    {
        return $this->hasMany(WorkflowTrigger::class);
    }

    /**
     * @return HasMany<WorkflowCondition, $this>
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(WorkflowCondition::class)->orderBy('group')->orderBy('order');
    }

    /**
     * @return HasMany<WorkflowAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class)->orderBy('order');
    }

    /**
     * @return HasMany<WorkflowExecution, $this>
     */
    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    /**
     * Check if the workflow is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Activate the workflow.
     */
    public function activate(): static
    {
        $this->update(['is_active' => true]);

        return $this;
    }

    /**
     * Deactivate the workflow.
     */
    public function deactivate(): static
    {
        $this->update(['is_active' => false]);

        return $this;
    }

    /**
     * Get a specific setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a specific setting value.
     */
    public function setSetting(string $key, mixed $value): static
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;

        return $this;
    }

    /**
     * Scope to only active workflows.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Workflow> $query
     * @return \Illuminate\Database\Eloquent\Builder<Workflow>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to workflows with a specific trigger type.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Workflow> $query
     * @return \Illuminate\Database\Eloquent\Builder<Workflow>
     */
    public function scopeWithTriggerType($query, string $type)
    {
        return $query->whereHas('triggers', function ($q) use ($type) {
            $q->where('type', $type);
        });
    }
}
