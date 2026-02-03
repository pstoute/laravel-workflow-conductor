<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor;

use Pstoute\WorkflowConductor\Models\Workflow;
use Pstoute\WorkflowConductor\Models\WorkflowAction;
use Pstoute\WorkflowConductor\Models\WorkflowCondition;
use Pstoute\WorkflowConductor\Models\WorkflowTrigger;

class WorkflowBuilder
{
    protected ?string $name = null;

    protected ?string $description = null;

    protected bool $isActive = true;

    /**
     * @var array<string, mixed>
     */
    protected array $settings = [];

    /**
     * @var array<int, array{type: string, configuration: array<string, mixed>}>
     */
    protected array $triggers = [];

    /**
     * @var array<int, array{type: string, field: string|null, operator: string, value: mixed, logic: string, group: int}>
     */
    protected array $conditions = [];

    /**
     * @var array<int, array{type: string, configuration: array<string, mixed>, delay: int, continue_on_failure: bool}>
     */
    protected array $actions = [];

    protected int $conditionGroup = 0;

    protected int $conditionOrder = 0;

    protected int $actionOrder = 0;

    public function __construct(protected WorkflowManager $manager)
    {
    }

    /**
     * Set the workflow name.
     */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the workflow description.
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set whether the workflow is active.
     */
    public function active(bool $active = true): static
    {
        $this->isActive = $active;

        return $this;
    }

    /**
     * Set workflow settings.
     *
     * @param array<string, mixed> $settings
     */
    public function settings(array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    /**
     * Add a trigger to the workflow.
     *
     * @param array<string, mixed> $configuration
     */
    public function trigger(string $type, array $configuration = []): static
    {
        $this->triggers[] = [
            'type' => $type,
            'configuration' => $configuration,
        ];

        return $this;
    }

    /**
     * Add a condition to the workflow.
     *
     * @param array<string, mixed> $config
     */
    public function condition(string $type, array $config = []): static
    {
        $this->conditions[] = [
            'type' => $type,
            'field' => $config['field'] ?? null,
            'operator' => $config['operator'] ?? 'equals',
            'value' => $config['value'] ?? null,
            'logic' => 'and',
            'group' => $this->conditionGroup,
            'order' => $this->conditionOrder++,
        ];

        return $this;
    }

    /**
     * Add an OR condition to the workflow.
     *
     * @param array<string, mixed> $config
     */
    public function orCondition(string $type, array $config = []): static
    {
        $this->conditions[] = [
            'type' => $type,
            'field' => $config['field'] ?? null,
            'operator' => $config['operator'] ?? 'equals',
            'value' => $config['value'] ?? null,
            'logic' => 'or',
            'group' => $this->conditionGroup,
            'order' => $this->conditionOrder++,
        ];

        return $this;
    }

    /**
     * Start a new condition group (for complex AND/OR logic).
     */
    public function conditionGroup(): static
    {
        $this->conditionGroup++;

        return $this;
    }

    /**
     * Add a field condition (shorthand).
     */
    public function when(string $field, string $operator, mixed $value = null): static
    {
        return $this->condition('field', [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        ]);
    }

    /**
     * Add an OR field condition (shorthand).
     */
    public function orWhen(string $field, string $operator, mixed $value = null): static
    {
        return $this->orCondition('field', [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        ]);
    }

    /**
     * Add an action to the workflow.
     *
     * @param array<string, mixed> $configuration
     */
    public function action(string $type, array $configuration = [], int $delay = 0, bool $continueOnFailure = true): static
    {
        $this->actions[] = [
            'type' => $type,
            'configuration' => $configuration,
            'delay' => $delay,
            'continue_on_failure' => $continueOnFailure,
            'order' => $this->actionOrder++,
        ];

        return $this;
    }

    /**
     * Add a send email action (shorthand).
     *
     * @param array<string, mixed> $options
     */
    public function sendEmail(string $to, string $subject, string $body, array $options = []): static
    {
        return $this->action('send_email', array_merge([
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ], $options));
    }

    /**
     * Add a webhook action (shorthand).
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    public function webhook(string $url, array $payload = [], array $options = []): static
    {
        return $this->action('webhook', array_merge([
            'url' => $url,
            'payload' => $payload,
        ], $options));
    }

    /**
     * Add a Slack message action (shorthand).
     *
     * @param array<string, mixed> $options
     */
    public function slack(string $message, ?string $channel = null, array $options = []): static
    {
        return $this->action('slack', array_merge([
            'message' => $message,
            'channel' => $channel,
        ], $options));
    }

    /**
     * Add a delay action (shorthand).
     */
    public function delay(int $duration, string $unit = 'seconds'): static
    {
        return $this->action('delay', [
            'duration' => $duration,
            'unit' => $unit,
        ]);
    }

    /**
     * Add a create model action (shorthand).
     *
     * @param array<string, mixed> $attributes
     */
    public function createModel(string $model, array $attributes): static
    {
        return $this->action('create_model', [
            'model' => $model,
            'attributes' => $attributes,
        ]);
    }

    /**
     * Add an update model action (shorthand).
     *
     * @param array<string, mixed> $attributes
     */
    public function updateModel(array $attributes, string $modelPath = 'model'): static
    {
        return $this->action('update_model', [
            'model' => $modelPath,
            'attributes' => $attributes,
        ]);
    }

    /**
     * Save the workflow to the database.
     */
    public function save(): Workflow
    {
        $workflow = Workflow::create([
            'name' => $this->name ?? 'Untitled Workflow',
            'description' => $this->description,
            'is_active' => $this->isActive,
            'settings' => $this->settings ?: null,
        ]);

        // Create triggers
        foreach ($this->triggers as $trigger) {
            WorkflowTrigger::create([
                'workflow_id' => $workflow->id,
                'type' => $trigger['type'],
                'configuration' => $trigger['configuration'],
            ]);
        }

        // Create conditions
        foreach ($this->conditions as $condition) {
            WorkflowCondition::create([
                'workflow_id' => $workflow->id,
                'type' => $condition['type'],
                'field' => $condition['field'],
                'operator' => $condition['operator'],
                'value' => $condition['value'],
                'logic' => $condition['logic'],
                'group' => $condition['group'],
                'order' => $condition['order'],
            ]);
        }

        // Create actions
        foreach ($this->actions as $action) {
            WorkflowAction::create([
                'workflow_id' => $workflow->id,
                'type' => $action['type'],
                'configuration' => $action['configuration'],
                'order' => $action['order'],
                'delay' => $action['delay'],
                'continue_on_failure' => $action['continue_on_failure'],
            ]);
        }

        return $workflow->load(['triggers', 'conditions', 'actions']);
    }
}
