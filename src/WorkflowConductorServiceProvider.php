<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor;

use Illuminate\Support\ServiceProvider;
use Pstoute\WorkflowConductor\Actions\CreateModelAction;
use Pstoute\WorkflowConductor\Actions\CustomAction;
use Pstoute\WorkflowConductor\Actions\DelayAction;
use Pstoute\WorkflowConductor\Actions\DeleteModelAction;
use Pstoute\WorkflowConductor\Actions\HttpRequestAction;
use Pstoute\WorkflowConductor\Actions\SendEmailAction;
use Pstoute\WorkflowConductor\Actions\SendNotificationAction;
use Pstoute\WorkflowConductor\Actions\SlackMessageAction;
use Pstoute\WorkflowConductor\Actions\UpdateModelAction;
use Pstoute\WorkflowConductor\Actions\WebhookAction;
use Pstoute\WorkflowConductor\Actions\ArrayAction;
use Pstoute\WorkflowConductor\Actions\DateFormatterAction;
use Pstoute\WorkflowConductor\Actions\GoalEventAction;
use Pstoute\WorkflowConductor\Actions\GoToAction;
use Pstoute\WorkflowConductor\Actions\IfElseAction;
use Pstoute\WorkflowConductor\Actions\MathAction;
use Pstoute\WorkflowConductor\Actions\NumberFormatterAction;
use Pstoute\WorkflowConductor\Actions\SetVariableAction;
use Pstoute\WorkflowConductor\Actions\SplitAction;
use Pstoute\WorkflowConductor\Actions\TextFormatterAction;
use Pstoute\WorkflowConductor\Conditions\CustomCondition;
use Pstoute\WorkflowConductor\Conditions\DateCondition;
use Pstoute\WorkflowConductor\Conditions\FieldCondition;
use Pstoute\WorkflowConductor\Conditions\RelationCondition;
use Pstoute\WorkflowConductor\Contracts\WorkflowExecutorInterface;
use Pstoute\WorkflowConductor\Engine\WorkflowEngine;
use Pstoute\WorkflowConductor\Triggers\ManualTrigger;
use Pstoute\WorkflowConductor\Triggers\ModelCreatedTrigger;
use Pstoute\WorkflowConductor\Triggers\ModelDeletedTrigger;
use Pstoute\WorkflowConductor\Triggers\ModelUpdatedTrigger;
use Pstoute\WorkflowConductor\Triggers\ScheduledTrigger;
use Pstoute\WorkflowConductor\Triggers\WebhookTrigger;

class WorkflowConductorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workflow-conductor.php', 'workflow-conductor');

        $this->app->singleton(WorkflowManager::class, function ($app) {
            return new WorkflowManager($app);
        });

        $this->app->alias(WorkflowManager::class, 'workflow-conductor');

        $this->app->singleton(WorkflowExecutorInterface::class, WorkflowEngine::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/workflow-conductor.php' => config_path('workflow-conductor.php'),
        ], 'workflow-conductor-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'workflow-conductor-migrations');

        if (!config('workflow-conductor.database.skip_migrations', false)) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');

        $this->registerBuiltInTriggers();
        $this->registerBuiltInConditions();
        $this->registerBuiltInActions();
    }

    protected function registerBuiltInTriggers(): void
    {
        $manager = $this->app->make(WorkflowManager::class);
        $config = config('workflow-conductor.triggers', []);

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
        $config = config('workflow-conductor.actions', []);

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

        // Utility actions
        if ($config['math']['enabled'] ?? true) {
            $manager->registerAction(new MathAction());
        }

        if ($config['date_formatter']['enabled'] ?? true) {
            $manager->registerAction(new DateFormatterAction());
        }

        if ($config['number_formatter']['enabled'] ?? true) {
            $manager->registerAction(new NumberFormatterAction());
        }

        if ($config['text_formatter']['enabled'] ?? true) {
            $manager->registerAction(new TextFormatterAction());
        }

        if ($config['array']['enabled'] ?? true) {
            $manager->registerAction(new ArrayAction());
        }

        if ($config['set_variable']['enabled'] ?? true) {
            $manager->registerAction(new SetVariableAction());
        }

        // Control flow actions
        if ($config['if_else']['enabled'] ?? true) {
            $manager->registerAction(new IfElseAction());
        }

        if ($config['split']['enabled'] ?? true) {
            $manager->registerAction(new SplitAction());
        }

        if ($config['goto']['enabled'] ?? true) {
            $manager->registerAction(new GoToAction());
        }

        if ($config['goal']['enabled'] ?? true) {
            $manager->registerAction(new GoalEventAction());
        }
    }
}
