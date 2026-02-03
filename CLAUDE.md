# Laravel Workflows - Package Development Guidelines

## Package Overview

**Package Name:** `pstoute/laravel-workflows`
**Description:** A powerful, Laravel-native workflow automation engine that enables developers to create complex automation rules with triggers, conditions, and actions. Think Zapier/Make/n8n but self-hosted and deeply integrated with Laravel.

**Target Audience:** Developers building SaaS applications, CRM systems, marketing automation, or any application requiring user-configurable automation workflows.

## Source Reference

This package is being extracted from the Tomo-Agency codebase. Reference files are located at:
- **Source Codebase:** `/Users/paulstoute/Sites/Tomo-Agency`
- **Core Engine:** `app/Services/WorkflowEngine.php`
- **Action Executor:** `app/Services/ActionExecutor.php`
- **Condition Evaluator:** `app/Services/ConditionEvaluator.php`
- **Related Models:** `app/Models/Automation.php`, `app/Models/AutomationAction.php`, `app/Models/AutomationCondition.php`, `app/Models/AutomationExecution.php`
- **Jobs:** `app/Jobs/ExecuteAutomation.php`, `app/Jobs/ExecuteAutomationAction.php`
- **React Components:** `resources/js/components/workflow-builder/`

## Package Architecture

### Directory Structure

```
laravel-workflows/
├── src/
│   ├── Contracts/
│   │   ├── TriggerInterface.php
│   │   ├── ConditionInterface.php
│   │   ├── ActionInterface.php
│   │   └── WorkflowExecutorInterface.php
│   ├── Engine/
│   │   ├── WorkflowEngine.php
│   │   ├── TriggerManager.php
│   │   ├── ConditionEvaluator.php
│   │   └── ActionExecutor.php
│   ├── Triggers/
│   │   ├── AbstractTrigger.php
│   │   ├── ModelCreatedTrigger.php
│   │   ├── ModelUpdatedTrigger.php
│   │   ├── ModelDeletedTrigger.php
│   │   ├── ScheduledTrigger.php
│   │   ├── WebhookTrigger.php
│   │   └── ManualTrigger.php
│   ├── Conditions/
│   │   ├── AbstractCondition.php
│   │   ├── FieldCondition.php
│   │   ├── DateCondition.php
│   │   ├── RelationCondition.php
│   │   ├── CustomCondition.php
│   │   └── ConditionGroup.php (AND/OR logic)
│   ├── Actions/
│   │   ├── AbstractAction.php
│   │   ├── SendEmailAction.php
│   │   ├── SendNotificationAction.php
│   │   ├── WebhookAction.php
│   │   ├── HttpRequestAction.php
│   │   ├── SlackMessageAction.php
│   │   ├── CreateModelAction.php
│   │   ├── UpdateModelAction.php
│   │   ├── DeleteModelAction.php
│   │   ├── CreateTaskAction.php
│   │   ├── DelayAction.php
│   │   ├── ConditionalBranchAction.php
│   │   └── CustomAction.php
│   ├── Models/
│   │   ├── Workflow.php
│   │   ├── WorkflowTrigger.php
│   │   ├── WorkflowCondition.php
│   │   ├── WorkflowAction.php
│   │   ├── WorkflowExecution.php
│   │   └── WorkflowExecutionLog.php
│   ├── Jobs/
│   │   ├── ExecuteWorkflow.php
│   │   ├── ExecuteWorkflowAction.php
│   │   └── ProcessScheduledWorkflows.php
│   ├── Events/
│   │   ├── WorkflowStarted.php
│   │   ├── WorkflowCompleted.php
│   │   ├── WorkflowFailed.php
│   │   ├── ActionExecuted.php
│   │   └── ActionFailed.php
│   ├── Exceptions/
│   │   ├── WorkflowException.php
│   │   ├── ConditionException.php
│   │   ├── ActionException.php
│   │   └── TriggerException.php
│   ├── Data/
│   │   ├── WorkflowContext.php (DTO)
│   │   ├── ExecutionResult.php (DTO)
│   │   └── ActionResult.php (DTO)
│   ├── Traits/
│   │   └── HasWorkflows.php (for models)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── WorkflowController.php
│   │   │   └── WebhookTriggerController.php
│   │   └── Middleware/
│   │       └── ValidateWebhookSignature.php
│   ├── Facades/
│   │   └── Workflows.php
│   ├── WorkflowManager.php
│   └── WorkflowServiceProvider.php
├── config/
│   └── workflows.php
├── database/
│   └── migrations/
│       ├── create_workflows_table.php
│       ├── create_workflow_triggers_table.php
│       ├── create_workflow_conditions_table.php
│       ├── create_workflow_actions_table.php
│       ├── create_workflow_executions_table.php
│       └── create_workflow_execution_logs_table.php
├── resources/
│   └── views/
│       └── (optional Blade components for workflow builder)
├── tests/
│   ├── Unit/
│   ├── Feature/
│   └── Fixtures/
├── docs/
│   ├── installation.md
│   ├── configuration.md
│   ├── triggers.md
│   ├── conditions.md
│   ├── actions.md
│   ├── custom-extensions.md
│   └── examples.md
├── composer.json
├── README.md
├── LICENSE
├── CHANGELOG.md
└── .github/
    └── workflows/
        └── tests.yml
```

### Core Interface Design

```php
<?php

namespace Pstoute\LaravelWorkflows\Contracts;

interface TriggerInterface
{
    public function getIdentifier(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getConfigurationSchema(): array;
    public function shouldTrigger(WorkflowContext $context): bool;
    public function getAvailableData(): array; // Data keys available after trigger
}

interface ConditionInterface
{
    public function getIdentifier(): string;
    public function getName(): string;
    public function getOperators(): array;
    public function evaluate(WorkflowContext $context, array $config): bool;
}

interface ActionInterface
{
    public function getIdentifier(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getConfigurationSchema(): array;
    public function execute(WorkflowContext $context, array $config): ActionResult;
    public function supportsAsync(): bool;
    public function getTimeout(): int; // seconds
}
```

### Facade Usage Examples

```php
use Pstoute\LaravelWorkflows\Facades\Workflows;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

// Register custom trigger
Workflows::registerTrigger(new PaymentReceivedTrigger());

// Register custom action
Workflows::registerAction(new SendSmsAction());

// Manually trigger a workflow
Workflows::trigger('payment.received', new WorkflowContext([
    'payment' => $payment,
    'user' => $payment->user,
]));

// Execute workflow by ID
$result = Workflows::execute($workflowId, $context);

// Create workflow programmatically
$workflow = Workflows::create()
    ->name('Welcome Email')
    ->trigger('model.created', ['model' => User::class])
    ->condition('field', ['field' => 'email_verified_at', 'operator' => 'is_not_null'])
    ->action('send_email', [
        'template' => 'welcome',
        'to' => '{{ user.email }}',
    ])
    ->save();

// List all executions
$executions = Workflows::executions()
    ->forWorkflow($workflowId)
    ->failed()
    ->get();
```

### Model Integration

```php
use Pstoute\LaravelWorkflows\Traits\HasWorkflows;

class Order extends Model
{
    use HasWorkflows;

    // Automatically triggers workflows on model events
    protected static function booted()
    {
        static::created(fn($order) => $order->triggerWorkflows('created'));
        static::updated(fn($order) => $order->triggerWorkflows('updated'));
    }
}
```

## Development Guidelines

### Code Style
- Follow PSR-12 coding standards
- Use PHP 8.2+ features (readonly properties, enums, named arguments)
- All public methods must have return types
- Use Data Transfer Objects (DTOs) for context and results

### Variable Interpolation

Support Blade-like syntax in action configurations:

```php
// In configuration
'subject' => 'Order #{{ order.number }} confirmed'
'to' => '{{ user.email }}'
'amount' => '{{ order.total | number_format:2 }}'

// Available filters
// | uppercase, | lowercase, | number_format:decimals
// | date:format, | default:value, | json
```

### Condition Operators

Standard operators for field conditions:
- `equals`, `not_equals`
- `contains`, `not_contains`
- `starts_with`, `ends_with`
- `greater_than`, `less_than`, `greater_or_equal`, `less_or_equal`
- `is_null`, `is_not_null`
- `is_empty`, `is_not_empty`
- `in`, `not_in` (for arrays)
- `matches_regex`

### Error Handling
- Each action should handle its own errors gracefully
- Failed actions should not stop the entire workflow (configurable)
- Log all execution steps for debugging
- Support retry logic for transient failures

### Execution Modes
- **Synchronous:** Execute immediately, block until complete
- **Asynchronous:** Queue for background processing
- **Delayed:** Execute after specified delay
- **Scheduled:** Execute at specific times (cron)

### Testing Requirements
- Unit tests for all triggers, conditions, and actions
- Feature tests for workflow execution
- Test condition evaluation with various operators
- Test action execution in isolation
- Test workflow execution end-to-end
- Minimum 80% code coverage

### Documentation Requirements
- README with quick start guide
- Comprehensive trigger documentation
- Condition operator reference
- Action configuration reference
- Guide for creating custom triggers/conditions/actions
- Example workflows for common use cases

## Configuration File Template

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Workflow Execution
    |--------------------------------------------------------------------------
    */
    'execution' => [
        'default_mode' => 'async', // sync, async
        'queue' => env('WORKFLOW_QUEUE', 'workflows'),
        'connection' => env('WORKFLOW_QUEUE_CONNECTION', 'default'),
        'max_retries' => 3,
        'retry_delay' => 60, // seconds
        'timeout' => 300, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'channel' => env('WORKFLOW_LOG_CHANNEL', 'stack'),
        'retention_days' => 30, // How long to keep execution logs
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'enabled' => true,
        'max_executions_per_minute' => 100,
        'max_actions_per_workflow' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Built-in Triggers
    |--------------------------------------------------------------------------
    */
    'triggers' => [
        'model_created' => true,
        'model_updated' => true,
        'model_deleted' => true,
        'scheduled' => true,
        'webhook' => true,
        'manual' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Built-in Actions
    |--------------------------------------------------------------------------
    */
    'actions' => [
        'send_email' => [
            'enabled' => true,
            'from' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
        ],
        'send_notification' => [
            'enabled' => true,
        ],
        'webhook' => [
            'enabled' => true,
            'timeout' => 30,
            'verify_ssl' => true,
        ],
        'http_request' => [
            'enabled' => true,
            'timeout' => 30,
            'allowed_hosts' => ['*'], // or specific hosts
        ],
        'slack' => [
            'enabled' => true,
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
        ],
        'create_model' => [
            'enabled' => true,
            'allowed_models' => ['*'], // or specific model classes
        ],
        'update_model' => [
            'enabled' => true,
            'allowed_models' => ['*'],
        ],
        'delay' => [
            'enabled' => true,
            'max_delay' => 86400 * 30, // 30 days max
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Triggers
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'route_prefix' => 'workflows/webhooks',
        'middleware' => ['api'],
        'signing_secret' => env('WORKFLOW_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'connection' => env('WORKFLOW_DB_CONNECTION', null), // null = default
        'table_prefix' => 'workflow_',
    ],
];
```

## Database Schema

```php
// workflows table
Schema::create('workflow_workflows', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->json('settings')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

// workflow_triggers table
Schema::create('workflow_triggers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workflow_id')->constrained('workflow_workflows')->cascadeOnDelete();
    $table->string('type'); // model_created, webhook, scheduled, etc.
    $table->json('configuration');
    $table->timestamps();
});

// workflow_conditions table
Schema::create('workflow_conditions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workflow_id')->constrained('workflow_workflows')->cascadeOnDelete();
    $table->string('type'); // field, date, relation, custom
    $table->string('field')->nullable();
    $table->string('operator');
    $table->json('value')->nullable();
    $table->string('logic')->default('and'); // and, or
    $table->integer('group')->default(0); // for grouping conditions
    $table->integer('order')->default(0);
    $table->timestamps();
});

// workflow_actions table
Schema::create('workflow_actions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workflow_id')->constrained('workflow_workflows')->cascadeOnDelete();
    $table->string('type'); // send_email, webhook, create_model, etc.
    $table->json('configuration');
    $table->integer('order')->default(0);
    $table->integer('delay')->default(0); // seconds
    $table->boolean('continue_on_failure')->default(true);
    $table->timestamps();
});

// workflow_executions table
Schema::create('workflow_executions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workflow_id')->constrained('workflow_workflows')->cascadeOnDelete();
    $table->string('trigger_type');
    $table->json('trigger_data')->nullable();
    $table->string('status'); // pending, running, completed, failed
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->json('result')->nullable();
    $table->text('error')->nullable();
    $table->timestamps();

    $table->index(['workflow_id', 'status']);
    $table->index('created_at');
});

// workflow_execution_logs table
Schema::create('workflow_execution_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('execution_id')->constrained('workflow_executions')->cascadeOnDelete();
    $table->foreignId('action_id')->nullable()->constrained('workflow_actions')->nullOnDelete();
    $table->string('type'); // trigger, condition, action
    $table->string('status'); // success, failed, skipped
    $table->json('input')->nullable();
    $table->json('output')->nullable();
    $table->text('error')->nullable();
    $table->integer('duration_ms')->nullable();
    $table->timestamps();

    $table->index('execution_id');
});
```

## Extraction Checklist

When extracting from the source codebase:

- [ ] Extract `WorkflowEngine.php` and refactor into modular components
- [ ] Extract `ActionExecutor.php` - convert to action registry pattern
- [ ] Extract `ConditionEvaluator.php` - support AND/OR groups
- [ ] Create `Automation` model adaptations as `Workflow` model
- [ ] Extract all action implementations and make them configurable
- [ ] Create trigger system (model events, scheduled, webhooks)
- [ ] Remove all Tomo-Agency specific dependencies
- [ ] Create variable interpolation system
- [ ] Build comprehensive test suite
- [ ] Create execution logging system
- [ ] Add rate limiting support
- [ ] Create documentation
- [ ] Optional: Extract React workflow builder as separate NPM package

## Dependencies

```json
{
    "require": {
        "php": "^8.2",
        "illuminate/support": "^10.0|^11.0|^12.0",
        "illuminate/contracts": "^10.0|^11.0|^12.0",
        "illuminate/database": "^10.0|^11.0|^12.0",
        "illuminate/queue": "^10.0|^11.0|^12.0",
        "illuminate/events": "^10.0|^11.0|^12.0",
        "guzzlehttp/guzzle": "^7.0"
    },
    "require-dev": {
        "orchestra/testbench": "^8.0|^9.0|^10.0",
        "phpunit/phpunit": "^10.0|^11.0",
        "mockery/mockery": "^1.6"
    }
}
```

## Example Workflows

### Welcome Email on User Registration
```php
$workflow = Workflows::create()
    ->name('Welcome Email')
    ->trigger('model.created', ['model' => User::class])
    ->condition('field', [
        'field' => 'email_verified_at',
        'operator' => 'is_not_null'
    ])
    ->action('send_email', [
        'to' => '{{ model.email }}',
        'subject' => 'Welcome to {{ config.app.name }}!',
        'template' => 'emails.welcome',
        'data' => ['user' => '{{ model }}']
    ])
    ->save();
```

### Slack Notification on High-Value Order
```php
$workflow = Workflows::create()
    ->name('High Value Order Alert')
    ->trigger('model.created', ['model' => Order::class])
    ->condition('field', [
        'field' => 'total',
        'operator' => 'greater_than',
        'value' => 1000
    ])
    ->action('slack', [
        'channel' => '#sales',
        'message' => ':moneybag: New order #{{ model.number }} for ${{ model.total | number_format:2 }} from {{ model.customer.name }}'
    ])
    ->save();
```

### Follow-up Email After 3 Days
```php
$workflow = Workflows::create()
    ->name('Follow-up Email')
    ->trigger('model.created', ['model' => Lead::class])
    ->action('delay', ['duration' => 259200]) // 3 days
    ->action('send_email', [
        'to' => '{{ model.email }}',
        'subject' => 'Just checking in...',
        'template' => 'emails.follow-up'
    ])
    ->save();
```

## Versioning

- Follow Semantic Versioning (SemVer)
- Major version bumps for breaking API changes
- Minor version bumps for new triggers/actions
- Patch version bumps for bug fixes

## License

MIT License - This package will be open source.
