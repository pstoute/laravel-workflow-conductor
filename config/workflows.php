<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Workflow Execution
    |--------------------------------------------------------------------------
    |
    | Configure how workflows are executed. You can set the default execution
    | mode (sync or async), queue settings, retry behavior, and timeouts.
    |
    */
    'execution' => [
        'default_mode' => env('WORKFLOW_EXECUTION_MODE', 'async'), // sync, async
        'queue' => env('WORKFLOW_QUEUE', 'workflows'),
        'connection' => env('WORKFLOW_QUEUE_CONNECTION', null), // null = default
        'max_retries' => (int) env('WORKFLOW_MAX_RETRIES', 3),
        'retry_delay' => (int) env('WORKFLOW_RETRY_DELAY', 60), // seconds
        'timeout' => (int) env('WORKFLOW_TIMEOUT', 300), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure workflow execution logging. Enable detailed logging to track
    | workflow executions and debug issues. Set retention to automatically
    | clean up old execution logs.
    |
    */
    'logging' => [
        'enabled' => env('WORKFLOW_LOGGING_ENABLED', true),
        'channel' => env('WORKFLOW_LOG_CHANNEL', 'stack'),
        'retention_days' => (int) env('WORKFLOW_LOG_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Protect your system from runaway workflows with rate limiting.
    | Configure maximum executions per minute and maximum actions per workflow.
    |
    */
    'rate_limits' => [
        'enabled' => env('WORKFLOW_RATE_LIMITS_ENABLED', true),
        'max_executions_per_minute' => (int) env('WORKFLOW_MAX_EXECUTIONS_PER_MINUTE', 100),
        'max_actions_per_workflow' => (int) env('WORKFLOW_MAX_ACTIONS', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Built-in Triggers
    |--------------------------------------------------------------------------
    |
    | Enable or disable built-in trigger types. Set any trigger to false
    | to prevent it from being registered.
    |
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
    |
    | Configure built-in action types. Each action can be enabled/disabled
    | and may have additional configuration options.
    |
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
            'verify_ssl' => env('WORKFLOW_WEBHOOK_VERIFY_SSL', true),
        ],

        'http_request' => [
            'enabled' => true,
            'timeout' => 30,
            'allowed_hosts' => ['*'], // ['*'] for all, or specific hosts
        ],

        'slack' => [
            'enabled' => true,
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
        ],

        'create_model' => [
            'enabled' => true,
            'allowed_models' => ['*'], // ['*'] for all, or specific model classes
        ],

        'update_model' => [
            'enabled' => true,
            'allowed_models' => ['*'],
        ],

        'delete_model' => [
            'enabled' => true,
            'allowed_models' => ['*'],
        ],

        'delay' => [
            'enabled' => true,
            'max_delay' => 86400 * 30, // 30 days max in seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Triggers
    |--------------------------------------------------------------------------
    |
    | Configure incoming webhook triggers. Webhooks allow external services
    | to trigger workflows via HTTP requests.
    |
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
    |
    | Configure database settings for workflow storage. You can use a
    | separate database connection and customize the table prefix.
    |
    */
    'database' => [
        'connection' => env('WORKFLOW_DB_CONNECTION', null), // null = default
        'table_prefix' => 'workflow_',
    ],
];
