<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows;

use Illuminate\Support\ServiceProvider;
use Pstoute\LaravelWorkflows\Actions\CreateModelAction;
use Pstoute\LaravelWorkflows\Actions\CustomAction;
use Pstoute\LaravelWorkflows\Actions\DelayAction;
use Pstoute\LaravelWorkflows\Actions\DeleteModelAction;
use Pstoute\LaravelWorkflows\Actions\HttpRequestAction;
use Pstoute\LaravelWorkflows\Actions\SendEmailAction;
use Pstoute\LaravelWorkflows\Actions\SendNotificationAction;
use Pstoute\LaravelWorkflows\Actions\SlackMessageAction;
use Pstoute\LaravelWorkflows\Actions\UpdateModelAction;
use Pstoute\LaravelWorkflows\Actions\WebhookAction;
use Pstoute\LaravelWorkflows\Conditions\CustomCondition;
use Pstoute\LaravelWorkflows\Conditions\DateCondition;
use Pstoute\LaravelWorkflows\Conditions\FieldCondition;
use Pstoute\LaravelWorkflows\Conditions\RelationCondition;
use Pstoute\LaravelWorkflows\Contracts\WorkflowExecutorInterface;
use Pstoute\LaravelWorkflows\Engine\WorkflowEngine;
use Pstoute\LaravelWorkflows\Triggers\ManualTrigger;
use Pstoute\LaravelWorkflows\Triggers\ModelCreatedTrigger;
use Pstoute\LaravelWorkflows\Triggers\ModelDeletedTrigger;
use Pstoute\LaravelWorkflows\Triggers\ModelUpdatedTrigger;
use Pstoute\LaravelWorkflows\Triggers\ScheduledTrigger;
use Pstoute\LaravelWorkflows\Triggers\WebhookTrigger;

class WorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workflows.php', 'workflows');

        $this->app->singleton(WorkflowManager::class, function ($app) {
            return new WorkflowManager($app);
        });

        $this->app->alias(WorkflowManager::class, 'workflows');

        $this->app->singleton(WorkflowExecutorInterface::class, WorkflowEngine::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/workflows.php' => config_path('workflows.php'),
        ], 'workflows-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'workflows-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');

        $this->registerBuiltInTriggers();
        $this->registerBuiltInConditions();
        $this->registerBuiltInActions();
    }

    protected function registerBuiltInTriggers(): void
    {
        $manager = $this->app->make(WorkflowManager::class);
        $config = config('workflows.triggers', []);

        if ($config['model_created'] ?? true) {
            $manager->registerTrigger(new ModelCreatedTrigger());
        }

        if ($config['model_updated'] ?? true) {
            $manager->registerTrigger(new ModelUpdatedTrigger());
        }

        if ($config['model_deleted'] ?? true) {
            $manager->registerTrigger(new ModelDeletedTrigger());
        }

        if ($config['scheduled'] ?? true) {
            $manager->registerTrigger(new ScheduledTrigger());
        }

        if ($config['webhook'] ?? true) {
            $manager->registerTrigger(new WebhookTrigger());
        }

        if ($config['manual'] ?? true) {
            $manager->registerTrigger(new ManualTrigger());
        }
    }

    protected function registerBuiltInConditions(): void
    {
        $manager = $this->app->make(WorkflowManager::class);

        $manager->registerCondition(new FieldCondition());
        $manager->registerCondition(new DateCondition());
        $manager->registerCondition(new RelationCondition());
        $manager->registerCondition(new CustomCondition());
    }

    protected function registerBuiltInActions(): void
    {
        $manager = $this->app->make(WorkflowManager::class);
        $config = config('workflows.actions', []);

        if ($config['send_email']['enabled'] ?? true) {
            $manager->registerAction(new SendEmailAction());
        }

        if ($config['send_notification']['enabled'] ?? true) {
            $manager->registerAction(new SendNotificationAction());
        }

        if ($config['webhook']['enabled'] ?? true) {
            $manager->registerAction(new WebhookAction());
        }

        if ($config['http_request']['enabled'] ?? true) {
            $manager->registerAction(new HttpRequestAction());
        }

        if ($config['slack']['enabled'] ?? true) {
            $manager->registerAction(new SlackMessageAction());
        }

        if ($config['create_model']['enabled'] ?? true) {
            $manager->registerAction(new CreateModelAction());
        }

        if ($config['update_model']['enabled'] ?? true) {
            $manager->registerAction(new UpdateModelAction());
        }

        if ($config['delete_model']['enabled'] ?? true) {
            $manager->registerAction(new DeleteModelAction());
        }

        if ($config['delay']['enabled'] ?? true) {
            $manager->registerAction(new DelayAction());
        }

        $manager->registerAction(new CustomAction());
    }
}
