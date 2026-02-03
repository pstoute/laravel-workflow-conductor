# Laravel Workflows

A powerful, Laravel-native workflow automation engine that enables developers to create complex automation rules with triggers, conditions, and actions. Think Zapier/Make/n8n but self-hosted and deeply integrated with Laravel.

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
composer require pstoute/laravel-workflows
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=workflows-config
```

Run the migrations:

```bash
php artisan migrate
```

## Quick Start

### Creating a Workflow Programmatically

```php
use Pstoute\LaravelWorkflows\Facades\Workflows;

// Create a welcome email workflow
$workflow = Workflows::create()
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
use Pstoute\LaravelWorkflows\Traits\HasWorkflows;

class User extends Model
{
    use HasWorkflows;
}
```

### Manual Workflow Execution

```php
use Pstoute\LaravelWorkflows\Facades\Workflows;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

$context = new WorkflowContext([
    'order' => $order,
    'user' => $order->user,
]);

// Execute synchronously
$result = Workflows::execute($workflowId, $context);

// Execute asynchronously
Workflows::executeAsync($workflowId, $context);
```

## Configuration

The configuration file is located at `config/workflows.php`. Key options include:

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
Workflows::create()
    ->trigger('model.created', ['model' => App\Models\User::class])
    // ...
```

Available model triggers:
- `model.created` - When a model is created
- `model.updated` - When a model is updated (optionally watch specific fields)
- `model.deleted` - When a model is deleted

### Scheduled (Cron)

```php
Workflows::create()
    ->trigger('scheduled', [
        'cron' => '0 9 * * *', // Every day at 9 AM
        'timezone' => 'America/New_York',
    ])
    // ...
```

### Webhook

```php
$workflow = Workflows::create()
    ->trigger('webhook', [
        'webhook_id' => 'your-unique-webhook-id',
    ])
    // ...
    ->save();

// Webhook URL: https://yourapp.com/workflows/webhooks/your-unique-webhook-id
```

### Manual

```php
Workflows::create()
    ->trigger('manual', [
        'allowed_users' => [1, 2, 3], // Optional: restrict to specific users
        'required_data' => ['order_id'], // Optional: require specific context data
    ])
    // ...
```

## Conditions

### Field Conditions

```php
Workflows::create()
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
Workflows::create()
    ->condition('date', [
        'field' => 'created_at',
        'operator' => 'is_today',
    ])
    // ...
```

### Relation Conditions

```php
Workflows::create()
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
Workflows::create()
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
Workflows::create()
    ->action('send_notification', [
        'notification' => App\Notifications\OrderShipped::class,
        'notifiable' => 'model.user',
    ])
```

### Webhook

```php
Workflows::create()
    ->webhook('https://api.example.com/webhook', [
        'event' => 'order.created',
        'order_id' => '{{ model.id }}',
    ])
```

### Slack Message

```php
Workflows::create()
    ->slack(
        ':money_bag: New order #{{ model.number }} for ${{ model.total | number_format:2 }}',
        '#sales'
    )
```

### Create/Update/Delete Model

```php
Workflows::create()
    ->createModel(App\Models\Task::class, [
        'user_id' => '{{ model.id }}',
        'title' => 'Follow up with {{ model.name }}',
    ])
    ->updateModel(['status' => 'processed'])
```

### Delay

```php
Workflows::create()
    ->delay(3, 'days')
    ->sendEmail(/* ... */)
```

### HTTP Request

```php
Workflows::create()
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
use Pstoute\LaravelWorkflows\Events\WorkflowCompleted;

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

use Pstoute\LaravelWorkflows\Contracts\TriggerInterface;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

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
use Pstoute\LaravelWorkflows\Facades\Workflows;
use App\Workflows\Triggers\PaymentReceivedTrigger;

public function boot(): void
{
    Workflows::registerTrigger(new PaymentReceivedTrigger());
}
```

Then trigger it from your code:

```php
use Pstoute\LaravelWorkflows\Facades\Workflows;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

// In your payment processing code
$context = new WorkflowContext([
    'payment' => $payment,
    'user' => $payment->user,
]);

Workflows::trigger('payment.received', $context);
```

### Creating a Custom Action

To create a custom action, implement `ActionInterface`:

```php
<?php

namespace App\Workflows\Actions;

use Pstoute\LaravelWorkflows\Contracts\ActionInterface;
use Pstoute\LaravelWorkflows\Data\ActionResult;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;
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
use Pstoute\LaravelWorkflows\Facades\Workflows;
use App\Workflows\Actions\SendSmsAction;

public function boot(): void
{
    Workflows::registerAction(app(SendSmsAction::class));
}
```

Now you can use it in workflows:

```php
Workflows::create()
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

use Pstoute\LaravelWorkflows\Contracts\ConditionInterface;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;

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
Workflows::registerCondition(new BusinessHoursCondition());

Workflows::create()
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
use Pstoute\LaravelWorkflows\Actions\CustomAction;
use Pstoute\LaravelWorkflows\Conditions\CustomCondition;

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
Workflows::create()
    ->name('Premium User Sync')
    ->trigger('model.updated', ['model' => App\Models\User::class])
    ->condition('custom', ['callback' => 'is_premium_user'])
    ->action('custom', ['handler' => 'sync_to_crm'])
    ->save();
```

## Scheduled Workflows

To process scheduled workflows, add this to your `app/Console/Kernel.php`:

```php
use Pstoute\LaravelWorkflows\Jobs\ProcessScheduledWorkflows;

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
