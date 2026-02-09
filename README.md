# Laravel Workflow Conductor

A powerful, Laravel-native workflow conductor that lets users build automation with triggers, conditions, and actions. Think Zapier/Make/n8n but self-hosted and deeply integrated with Laravel.

## Features

- **Triggers**: Model events, webhooks, scheduled (cron), manual
- **Conditions**: Field comparisons, date conditions, relation checks, custom callbacks
- **Actions**: Send emails, notifications, webhooks, Slack messages, create/update/delete models
- **Variable Interpolation**: Use `{{ model.field }}` syntax with filters
- **Condition Logic**: AND/OR grouping for complex conditions
- **Async Execution**: Queue-based execution with retry support
- **Execution Logging**: Track every workflow execution and action result
- **Extensible**: Easily add custom triggers, conditions, and actions

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x, or 12.x

## Installation

```bash
composer require pstoute/laravel-workflow-conductor
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=workflow-conductor-config
```

Run the migrations:

```bash
php artisan migrate
```

## Using with an Existing Schema

If your application already has workflow/automation tables, you can skip the package migrations and map your models:

```php
// config/workflow-conductor.php

'database' => [
    'table_prefix' => 'automation_',   // Match your existing table prefix
    'skip_migrations' => true,          // Skip package migrations
],

// Override the default models with your extended versions
'models' => [
    'workflow' => App\Models\Automation::class,
    'trigger' => App\Models\AutomationTrigger::class,
    'condition' => App\Models\AutomationCondition::class,
    'action' => App\Models\AutomationAction::class,
    'execution' => App\Models\AutomationExecution::class,
    'execution_log' => App\Models\AutomationActionResult::class,
],
```

Your models should extend the package models and override `getTable()`:

```php
use Pstoute\WorkflowConductor\Models\Workflow;

class Automation extends Workflow
{
    public function getTable(): string
    {
        return 'automations';
    }

    // Add your custom columns, relationships, etc.
}
```

If you are using `dont-discover` to prevent auto-registration, create a wrapper provider:

```php
use Pstoute\WorkflowConductor\WorkflowConductorServiceProvider;

class WorkflowConductorProvider extends WorkflowConductorServiceProvider
{
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            base_path('vendor/pstoute/laravel-workflow-conductor/config/workflow-conductor.php')
                => config_path('workflow-conductor.php'),
        ], 'workflow-conductor-config');

        // Skip migrations - the app has its own tables
        // Load routes and register built-in extensions
        $this->loadRoutesFrom(base_path('vendor/pstoute/laravel-workflow-conductor/routes/webhooks.php'));
        $this->registerBuiltInTriggers();
        $this->registerBuiltInConditions();
        $this->registerBuiltInActions();
    }
}
```

## Quick Start

### Creating a Workflow Programmatically

```php
use Pstoute\WorkflowConductor\Facades\Conductor;

// Create a welcome email workflow
$workflow = Conductor::create()
    ->name('Welcome Email')
    ->trigger('model.created', ['model' => App\Models\User::class])
    ->when('email_verified_at', 'is_not_null')
    ->sendEmail(
        '{{ model.email }}',
        'Welcome to {{ config.app.name }}!',
        'Hello {{ model.name }}!'
    )
    ->save();
```

### Using the HasWorkflows Trait

Add the trait to your models to automatically trigger workflows on model events:

```php
use Pstoute\WorkflowConductor\Traits\HasWorkflows;

class User extends Model
{
    use HasWorkflows;
}
```

### Manual Workflow Execution

```php
use Pstoute\WorkflowConductor\Facades\Conductor;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

$context = new WorkflowContext([
    'order' => $order,
    'user' => $order->user,
]);

// Execute synchronously
$result = Conductor::execute($workflowId, $context);

// Execute asynchronously
Conductor::executeAsync($workflowId, $context);
```

## Configuration

The configuration file is located at `config/workflow-conductor.php`. Key options include:

```php
return [
    'execution' => [
        'default_mode' => 'async', // sync or async
        'queue' => 'workflows',
        'max_retries' => 3,
        'timeout' => 300,
    ],

    'logging' => [
        'enabled' => true,
        'retention_days' => 30,
    ],

    'rate_limits' => [
        'enabled' => true,
        'max_executions_per_minute' => 100,
    ],
];
```

## Triggers

### Model Events

```php
Conductor::create()
    ->trigger('model.created', ['model' => App\Models\User::class])
    // ...
```

Available model triggers:
- `model.created` - When a model is created
- `model.updated` - When a model is updated (optionally watch specific fields)
- `model.deleted` - When a model is deleted

### Scheduled (Cron)

```php
Conductor::create()
    ->trigger('scheduled', [
        'cron' => '0 9 * * *', // Every day at 9 AM
        'timezone' => 'America/New_York',
    ])
    // ...
```

### Webhook

```php
$workflow = Conductor::create()
    ->trigger('webhook', [
        'webhook_id' => 'your-unique-webhook-id',
    ])
    // ...
    ->save();

// Webhook URL: https://yourapp.com/workflows/webhooks/your-unique-webhook-id
```

### Manual

```php
Conductor::create()
    ->trigger('manual', [
        'allowed_users' => [1, 2, 3], // Optional: restrict to specific users
        'required_data' => ['order_id'], // Optional: require specific context data
    ])
    // ...
```

## Conditions

### Field Conditions

```php
Conductor::create()
    ->when('status', 'equals', 'active')
    ->when('total', 'greater_than', 100)
    ->orWhen('type', 'equals', 'premium')
    // ...
```

Available operators:
- `equals`, `not_equals`
- `contains`, `not_contains`
- `starts_with`, `ends_with`
- `greater_than`, `less_than`, `greater_or_equal`, `less_or_equal`
- `is_null`, `is_not_null`
- `is_empty`, `is_not_empty`
- `in`, `not_in`
- `matches_regex`
- `between`

### Date Conditions

```php
Conductor::create()
    ->condition('date', [
        'field' => 'created_at',
        'operator' => 'is_today',
    ])
    // ...
```

### Relation Conditions

```php
Conductor::create()
    ->condition('relation', [
        'relation' => 'orders',
        'operator' => 'count_greater',
        'value' => 5,
    ])
    // ...
```

## Actions

### Send Email

```php
Conductor::create()
    ->sendEmail(
        '{{ model.email }}',
        'Order Confirmation #{{ model.order_number }}',
        '<p>Thank you for your order!</p>'
    )
    // Or with template
    ->action('send_email', [
        'to' => '{{ model.email }}',
        'subject' => 'Welcome!',
        'template' => 'emails.welcome',
        'data' => ['user' => '{{ model }}'],
    ])
```

### Send Notification

```php
Conductor::create()
    ->action('send_notification', [
        'notification' => App\Notifications\OrderShipped::class,
        'notifiable' => 'model.user',
    ])
```

### Webhook

```php
Conductor::create()
    ->webhook('https://api.example.com/webhook', [
        'event' => 'order.created',
        'order_id' => '{{ model.id }}',
    ])
```

### Slack Message

```php
Conductor::create()
    ->slack(
        ':money_bag: New order #{{ model.number }} for ${{ model.total | number_format:2 }}',
        '#sales'
    )
```

### Create/Update/Delete Model

```php
Conductor::create()
    ->createModel(App\Models\Task::class, [
        'user_id' => '{{ model.id }}',
        'title' => 'Follow up with {{ model.name }}',
    ])
    ->updateModel(['status' => 'processed'])
```

### Delay

```php
Conductor::create()
    ->delay(3, 'days')
    ->sendEmail(/* ... */)
```

### HTTP Request

```php
Conductor::create()
    ->action('http_request', [
        'url' => 'https://api.example.com/users',
        'method' => 'POST',
        'body' => ['email' => '{{ model.email }}'],
        'auth' => [
            'type' => 'bearer',
            'token' => '{{ env.API_TOKEN }}',
        ],
    ])
```

## Variable Interpolation

Use `{{ variable.path }}` syntax in action configurations:

```php
'subject' => 'Hello {{ user.name }}!'
'amount' => '{{ order.total | number_format:2 }}'
```

### Available Filters

| Filter | Description | Example |
|--------|-------------|---------|
| `uppercase` | Convert to uppercase | `{{ name \| uppercase }}` |
| `lowercase` | Convert to lowercase | `{{ name \| lowercase }}` |
| `number_format:N` | Format number with N decimals | `{{ price \| number_format:2 }}` |
| `date:format` | Format date | `{{ created_at \| date:Y-m-d }}` |
| `default:value` | Default if null | `{{ name \| default:Guest }}` |
| `json` | Convert to JSON | `{{ data \| json }}` |
| `slug` | Convert to slug | `{{ title \| slug }}` |
| `money` | Format as currency | `{{ price \| money }}` |
| `count` | Count array items | `{{ items \| count }}` |
| `join:separator` | Join array | `{{ tags \| join:, }}` |

## Events

The package dispatches events during workflow execution:

- `WorkflowStarted` - When a workflow begins execution
- `WorkflowCompleted` - When a workflow completes successfully
- `WorkflowFailed` - When a workflow fails
- `ActionExecuted` - When an action completes successfully
- `ActionFailed` - When an action fails

```php
use Pstoute\WorkflowConductor\Events\WorkflowCompleted;

Event::listen(WorkflowCompleted::class, function ($event) {
    Log::info("Workflow {$event->workflow->name} completed");
});
```

## Contributing Custom Extensions

### Creating a Custom Trigger

To create a custom trigger, implement `TriggerInterface`:

```php
<?php

namespace App\Workflows\Triggers;

use Pstoute\WorkflowConductor\Contracts\TriggerInterface;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class PaymentReceivedTrigger implements TriggerInterface
{
    public function getIdentifier(): string
    {
        return 'payment.received';
    }

    public function getName(): string
    {
        return 'Payment Received';
    }

    public function getDescription(): string
    {
        return 'Triggered when a payment is received';
    }

    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'min_amount' => [
                    'type' => 'number',
                    'description' => 'Minimum payment amount to trigger',
                ],
                'currency' => [
                    'type' => 'string',
                    'description' => 'Currency code (e.g., USD)',
                ],
            ],
        ];
    }

    public function shouldTrigger(WorkflowContext $context, array $triggerConfig): bool
    {
        $payment = $context->get('payment');

        if (!$payment) {
            return false;
        }

        // Check minimum amount
        $minAmount = $triggerConfig['min_amount'] ?? 0;
        if ($payment->amount < $minAmount) {
            return false;
        }

        // Check currency
        $currency = $triggerConfig['currency'] ?? null;
        if ($currency && $payment->currency !== $currency) {
            return false;
        }

        return true;
    }

    public function getAvailableData(): array
    {
        return [
            'payment' => 'The payment object',
            'payment.amount' => 'Payment amount',
            'payment.currency' => 'Payment currency',
            'payment.user' => 'The user who made the payment',
        ];
    }
}
```

Register your trigger in a service provider:

```php
use Pstoute\WorkflowConductor\Facades\Conductor;
use App\Workflows\Triggers\PaymentReceivedTrigger;

public function boot(): void
{
    Conductor::registerTrigger(new PaymentReceivedTrigger());
}
```

Then trigger it from your code:

```php
use Pstoute\WorkflowConductor\Facades\Conductor;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

// In your payment processing code
$context = new WorkflowContext([
    'payment' => $payment,
    'user' => $payment->user,
]);

Conductor::trigger('payment.received', $context);
```

### Creating a Custom Action

To create a custom action, implement `ActionInterface`:

```php
<?php

namespace App\Workflows\Actions;

use Pstoute\WorkflowConductor\Contracts\ActionInterface;
use Pstoute\WorkflowConductor\Data\ActionResult;
use Pstoute\WorkflowConductor\Data\WorkflowContext;
use App\Services\SmsService;

class SendSmsAction implements ActionInterface
{
    public function __construct(
        protected SmsService $smsService
    ) {}

    public function getIdentifier(): string
    {
        return 'send_sms';
    }

    public function getName(): string
    {
        return 'Send SMS';
    }

    public function getDescription(): string
    {
        return 'Send an SMS message via Twilio';
    }

    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'to' => [
                    'type' => 'string',
                    'description' => 'Phone number to send to',
                    'required' => true,
                ],
                'message' => [
                    'type' => 'string',
                    'description' => 'Message content',
                    'required' => true,
                ],
            ],
        ];
    }

    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $to = $config['to'] ?? null;
        $message = $config['message'] ?? null;

        if (empty($to) || empty($message)) {
            return ActionResult::failure('Phone number and message are required');
        }

        try {
            $result = $this->smsService->send($to, $message);

            return ActionResult::success('SMS sent successfully', [
                'message_sid' => $result->sid,
                'sent_to' => $to,
            ]);
        } catch (\Exception $e) {
            return ActionResult::failure('Failed to send SMS: ' . $e->getMessage(), $e);
        }
    }

    public function supportsAsync(): bool
    {
        return true;
    }

    public function getTimeout(): int
    {
        return 30;
    }

    public function getOutputData(): array
    {
        return [
            'message_sid' => 'The Twilio message SID',
            'sent_to' => 'The phone number the message was sent to',
        ];
    }
}
```

Register your action in a service provider:

```php
use Pstoute\WorkflowConductor\Facades\Conductor;
use App\Workflows\Actions\SendSmsAction;

public function boot(): void
{
    Conductor::registerAction(app(SendSmsAction::class));
}
```

Now you can use it in workflows:

```php
Conductor::create()
    ->name('Order SMS Notification')
    ->trigger('model.created', ['model' => App\Models\Order::class])
    ->action('send_sms', [
        'to' => '{{ model.user.phone }}',
        'message' => 'Your order #{{ model.number }} has been received!',
    ])
    ->save();
```

### Creating a Custom Condition

To create a custom condition, implement `ConditionInterface`:

```php
<?php

namespace App\Workflows\Conditions;

use Pstoute\WorkflowConductor\Contracts\ConditionInterface;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

class BusinessHoursCondition implements ConditionInterface
{
    public function getIdentifier(): string
    {
        return 'business_hours';
    }

    public function getName(): string
    {
        return 'Business Hours';
    }

    public function getDescription(): string
    {
        return 'Check if current time is within business hours';
    }

    public function getOperators(): array
    {
        return [
            'is_business_hours' => 'Is during business hours',
            'is_not_business_hours' => 'Is outside business hours',
        ];
    }

    public function evaluate(WorkflowContext $context, array $config): bool
    {
        $operator = $config['operator'] ?? 'is_business_hours';
        $timezone = $config['timezone'] ?? config('app.timezone');
        $startHour = $config['start_hour'] ?? 9;
        $endHour = $config['end_hour'] ?? 17;
        $workDays = $config['work_days'] ?? [1, 2, 3, 4, 5]; // Mon-Fri

        $now = now($timezone);
        $currentHour = $now->hour;
        $currentDay = $now->dayOfWeek;

        $isBusinessHours = in_array($currentDay, $workDays)
            && $currentHour >= $startHour
            && $currentHour < $endHour;

        return $operator === 'is_business_hours' ? $isBusinessHours : !$isBusinessHours;
    }

    public function getConfigurationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operator' => [
                    'type' => 'string',
                    'enum' => ['is_business_hours', 'is_not_business_hours'],
                ],
                'timezone' => [
                    'type' => 'string',
                    'description' => 'Timezone to check',
                ],
                'start_hour' => [
                    'type' => 'integer',
                    'description' => 'Start hour (0-23)',
                    'default' => 9,
                ],
                'end_hour' => [
                    'type' => 'integer',
                    'description' => 'End hour (0-23)',
                    'default' => 17,
                ],
            ],
        ];
    }
}
```

Register and use:

```php
Conductor::registerCondition(new BusinessHoursCondition());

Conductor::create()
    ->name('Business Hours Only')
    ->trigger('model.created', ['model' => App\Models\Lead::class])
    ->condition('business_hours', [
        'operator' => 'is_business_hours',
        'timezone' => 'America/New_York',
    ])
    ->action('send_notification', [/* ... */])
    ->save();
```

### Using Custom Handlers

For quick custom logic without creating full classes, use the `custom` action/condition:

```php
use Pstoute\WorkflowConductor\Actions\CustomAction;
use Pstoute\WorkflowConductor\Conditions\CustomCondition;

// Register a custom action handler
CustomAction::register('sync_to_crm', function (WorkflowContext $context, array $params) {
    $user = $context->get('model');
    // Sync logic here...
    return ActionResult::success('Synced to CRM', ['crm_id' => $crmId]);
});

// Register a custom condition
CustomCondition::register('is_premium_user', function (WorkflowContext $context, array $params) {
    $user = $context->get('model');
    return $user->subscription_type === 'premium';
});

// Use in workflow
Conductor::create()
    ->name('Premium User Sync')
    ->trigger('model.updated', ['model' => App\Models\User::class])
    ->condition('custom', ['callback' => 'is_premium_user'])
    ->action('custom', ['handler' => 'sync_to_crm'])
    ->save();
```

## Building an Extension Provider

For applications with multiple custom triggers and actions, organize them in a dedicated service provider:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Pstoute\WorkflowConductor\WorkflowManager;

class AutomationExtensionProvider extends ServiceProvider
{
    public function boot(): void
    {
        $manager = $this->app->make(WorkflowManager::class);

        // Register custom triggers
        $manager->registerTrigger(new \App\Automation\Triggers\PaymentReceivedTrigger());
        $manager->registerTrigger(new \App\Automation\Triggers\FormSubmittedTrigger());

        // Register custom actions with dependency injection
        $manager->registerAction($this->app->make(\App\Automation\Actions\SendSmsAction::class));
        $manager->registerAction($this->app->make(\App\Automation\Actions\SyncToCrmAction::class));
        $manager->registerAction($this->app->make(\App\Automation\Actions\GeneratePdfAction::class));
    }
}
```

Disable built-in actions that your app replaces to avoid duplicates:

```php
// config/workflow-conductor.php
'actions' => [
    'send_email' => ['enabled' => false],     // App provides enhanced version
    'webhook' => ['enabled' => false],          // App provides its own
    'create_model' => ['enabled' => true],      // Keep generic utilities
    'update_model' => ['enabled' => true],
    'delete_model' => ['enabled' => true],
],
```

## Action Output and Chaining

Each action's output is merged into the `WorkflowContext` under the `previous_actions` key, making it available to subsequent actions:

```php
// First action: generate a PDF
class GeneratePdfAction extends AbstractAction
{
    public function execute(WorkflowContext $context, array $config): ActionResult
    {
        $pdf = $this->pdfService->generate($config['template'], $context->all());

        return ActionResult::success('PDF generated', [
            'file_path' => $pdf->path,
            'filename' => $pdf->name,
            'download_url' => $pdf->url,
        ]);
    }
}

// Second action: send email with the PDF from the previous action
// In the action config, reference previous output:
[
    'to' => '{{ model.email }}',
    'subject' => 'Your document is ready',
    'body' => 'Download: {{ previous_actions.generate_pdf.download_url }}',
    'attachment' => '{{ previous_actions.generate_pdf.file_path }}',
]
```

## Querying the Registry

Use the `WorkflowManager` to list all registered extensions programmatically. This is useful for building UI dashboards where users configure workflows:

```php
use Pstoute\WorkflowConductor\WorkflowManager;

$manager = app(WorkflowManager::class);

// Get all registered actions with their schemas (for dropdown/form generation)
$actions = collect($manager->getActions())->map(fn ($action) => [
    'value' => $action->getIdentifier(),
    'label' => $action->getName(),
    'description' => $action->getDescription(),
    'schema' => $action->getConfigurationSchema(),
    'output' => $action->getOutputData(),
]);

// Get all registered triggers
$triggers = collect($manager->getTriggers())->map(fn ($trigger) => [
    'value' => $trigger->getIdentifier(),
    'label' => $trigger->getName(),
    'schema' => $trigger->getConfigurationSchema(),
    'available_data' => $trigger->getAvailableData(),
]);
```

## Using the VariableInterpolator

The `VariableInterpolator` resolves `{{ path }}` placeholders against a `WorkflowContext`. Use it to process action configs before execution:

```php
use Pstoute\WorkflowConductor\Support\VariableInterpolator;
use Pstoute\WorkflowConductor\Data\WorkflowContext;

$interpolator = new VariableInterpolator();
$context = new WorkflowContext(['user' => $user, 'order' => $order]);

// Interpolate strings
$greeting = $interpolator->interpolate('Hello {{ user.name }}!', $context);

// Interpolate arrays recursively
$config = $interpolator->interpolate([
    'to' => '{{ user.email }}',
    'subject' => 'Order #{{ order.number }}',
    'amount' => '{{ order.total | money:$:2 }}',
], $context);
```

### All Available Filters

| Filter | Description | Example |
|--------|-------------|---------|
| `uppercase` / `upper` | Uppercase | `{{ name \| uppercase }}` |
| `lowercase` / `lower` | Lowercase | `{{ name \| lowercase }}` |
| `ucfirst` / `capitalize` | Capitalize first letter | `{{ name \| ucfirst }}` |
| `ucwords` / `title` | Title case | `{{ name \| title }}` |
| `trim` | Trim whitespace | `{{ input \| trim }}` |
| `slug` | URL slug | `{{ title \| slug }}` |
| `snake` | snake_case | `{{ name \| snake }}` |
| `camel` | camelCase | `{{ name \| camel }}` |
| `studly` / `pascal` | StudlyCase | `{{ name \| studly }}` |
| `number_format:decimals` | Format number | `{{ price \| number_format:2 }}` |
| `date:format` | Format date | `{{ created_at \| date:M d, Y }}` |
| `default:value` | Fallback if null | `{{ name \| default:Guest }}` |
| `json` | JSON encode | `{{ data \| json }}` |
| `count` | Count items | `{{ items \| count }}` |
| `first` | First element | `{{ items \| first }}` |
| `last` | Last element | `{{ items \| last }}` |
| `join:sep` / `implode` | Join array | `{{ tags \| join:, }}` |
| `split:sep` / `explode` | Split string | `{{ csv \| split:, }}` |
| `length` / `strlen` | String/array length | `{{ name \| length }}` |
| `substr:start:len` | Substring | `{{ text \| substr:0:100 }}` |
| `replace:old:new` | Replace string | `{{ text \| replace:foo:bar }}` |
| `money:symbol:decimals` | Currency format | `{{ price \| money:$:2 }}` |
| `bool` / `int` / `float` / `string` | Type casting | `{{ value \| int }}` |
| `abs` / `round:n` / `floor` / `ceil` | Math operations | `{{ value \| round:2 }}` |

### Special Variable Prefixes

| Prefix | Description | Example |
|--------|-------------|---------|
| `config.` | Laravel config values | `{{ config.app.name }}` |
| `env.` | Environment variables | `{{ env.API_TOKEN }}` |
| `now` | Current timestamp | `{{ now \| date:Y-m-d }}` |

## Scheduled Workflows

To process scheduled workflows, add this to your `app/Console/Kernel.php`:

```php
use Pstoute\WorkflowConductor\Jobs\ProcessScheduledWorkflows;

protected function schedule(Schedule $schedule): void
{
    $schedule->job(new ProcessScheduledWorkflows())->everyMinute();
}
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
