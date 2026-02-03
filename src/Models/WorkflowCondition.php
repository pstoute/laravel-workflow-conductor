<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workflow_id
 * @property string $type
 * @property string|null $field
 * @property string $operator
 * @property mixed $value
 * @property string $logic
 * @property int $group
 * @property int $order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Workflow $workflow
 */
class WorkflowCondition extends Model
{
    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'json',
        'group' => 'integer',
        'order' => 'integer',
    ];

    public function getTable(): string
    {
        return config('workflow-conductor.database.table_prefix', 'workflow_') . 'conditions';
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
     * Check if this condition uses AND logic.
     */
    public function isAnd(): bool
    {
        return strtolower($this->logic) === 'and';
    }

    /**
     * Check if this condition uses OR logic.
     */
    public function isOr(): bool
    {
        return strtolower($this->logic) === 'or';
    }

    /**
     * Get the condition configuration as an array.
     *
     * @return array<string, mixed>
     */
    public function toConditionConfig(): array
    {
        return [
            'type' => $this->type,
            'field' => $this->field,
            'operator' => $this->operator,
            'value' => $this->value,
            'logic' => $this->logic,
            'group' => $this->group,
        ];
    }
}
