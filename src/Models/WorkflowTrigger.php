<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workflow_id
 * @property string $type
 * @property array<string, mixed> $configuration
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Workflow $workflow
 */
class WorkflowTrigger extends Model
{
    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'configuration' => 'array',
    ];

    public function getTable(): string
    {
        return config('workflow-conductor.database.table_prefix', 'workflow_') . 'triggers';
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
}
