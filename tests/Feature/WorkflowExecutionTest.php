<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Pstoute\LaravelWorkflows\Actions\CustomAction;
use Pstoute\LaravelWorkflows\Data\ActionResult;
use Pstoute\LaravelWorkflows\Data\WorkflowContext;
use Pstoute\LaravelWorkflows\Events\ActionExecuted;
use Pstoute\LaravelWorkflows\Events\WorkflowCompleted;
use Pstoute\LaravelWorkflows\Events\WorkflowStarted;
use Pstoute\LaravelWorkflows\Facades\Workflows;
use Pstoute\LaravelWorkflows\Models\Workflow;
use Pstoute\LaravelWorkflows\Models\WorkflowAction;
use Pstoute\LaravelWorkflows\Models\WorkflowCondition;
use Pstoute\LaravelWorkflows\Models\WorkflowExecution;
use Pstoute\LaravelWorkflows\Tests\TestCase;

class WorkflowExecutionTest extends TestCase
{
    public function test_it_executes_a_simple_workflow(): void
    {
        Event::fake([
            WorkflowStarted::class,
            WorkflowCompleted::class,
        ]);

        // Register custom action for testing
        CustomAction::register('test_action', function (WorkflowContext $context) {
            return ActionResult::success('Test action executed', [
                'received_name' => $context->get('name'),
            ]);
        });

        $workflow = Workflow::create([
            'name' => 'Test Workflow',
            'is_active' => true,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => ['handler' => 'test_action'],
            'order' => 0,
        ]);

        $context = new WorkflowContext(['name' => 'John']);

        $result = Workflows::execute($workflow->id, $context);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(1, $result->actionResults);
        $this->assertEquals('John', $result->actionResults[0]->output['received_name']);

        // Check execution was logged
        $this->assertDatabaseHas('workflow_executions', [
            'workflow_id' => $workflow->id,
            'status' => 'completed',
        ]);

        // Verify events were dispatched
        Event::assertDispatched(WorkflowStarted::class);
        Event::assertDispatched(WorkflowCompleted::class);
    }

    public function test_it_skips_workflow_when_conditions_not_met(): void
    {
        $workflow = Workflow::create([
            'name' => 'Conditional Workflow',
            'is_active' => true,
        ]);

        WorkflowCondition::create([
            'workflow_id' => $workflow->id,
            'type' => 'field',
            'field' => 'status',
            'operator' => 'equals',
            'value' => 'active',
            'logic' => 'and',
            'group' => 0,
            'order' => 0,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => ['handler' => 'test_action'],
            'order' => 0,
        ]);

        $context = new WorkflowContext(['status' => 'inactive']);

        $result = Workflows::execute($workflow->id, $context);

        $this->assertTrue($result->isSkipped());
        $this->assertEmpty($result->actionResults);
    }

    public function test_it_executes_workflow_when_conditions_met(): void
    {
        CustomAction::register('conditional_action', fn () => ActionResult::success('Condition passed'));

        $workflow = Workflow::create([
            'name' => 'Conditional Workflow',
            'is_active' => true,
        ]);

        WorkflowCondition::create([
            'workflow_id' => $workflow->id,
            'type' => 'field',
            'field' => 'amount',
            'operator' => 'greater_than',
            'value' => 100,
            'logic' => 'and',
            'group' => 0,
            'order' => 0,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => ['handler' => 'conditional_action'],
            'order' => 0,
        ]);

        $context = new WorkflowContext(['amount' => 150]);

        $result = Workflows::execute($workflow->id, $context);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(1, $result->actionResults);
    }

    public function test_it_handles_multiple_conditions_with_and_logic(): void
    {
        CustomAction::register('multi_condition_action', fn () => ActionResult::success());

        $workflow = Workflow::create([
            'name' => 'Multi Condition Workflow',
            'is_active' => true,
        ]);

        WorkflowCondition::create([
            'workflow_id' => $workflow->id,
            'type' => 'field',
            'field' => 'status',
            'operator' => 'equals',
            'value' => 'active',
            'logic' => 'and',
            'group' => 0,
            'order' => 0,
        ]);

        WorkflowCondition::create([
            'workflow_id' => $workflow->id,
            'type' => 'field',
            'field' => 'amount',
            'operator' => 'greater_than',
            'value' => 50,
            'logic' => 'and',
            'group' => 0,
            'order' => 1,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => ['handler' => 'multi_condition_action'],
            'order' => 0,
        ]);

        // Both conditions met
        $context = new WorkflowContext(['status' => 'active', 'amount' => 100]);
        $result = Workflows::execute($workflow->id, $context);
        $this->assertTrue($result->isSuccess());

        // One condition not met
        $context = new WorkflowContext(['status' => 'active', 'amount' => 30]);
        $result = Workflows::execute($workflow->id, $context);
        $this->assertTrue($result->isSkipped());
    }

    public function test_it_handles_or_conditions(): void
    {
        CustomAction::register('or_condition_action', fn () => ActionResult::success());

        $workflow = Workflow::create([
            'name' => 'OR Condition Workflow',
            'is_active' => true,
        ]);

        WorkflowCondition::create([
            'workflow_id' => $workflow->id,
            'type' => 'field',
            'field' => 'type',
            'operator' => 'equals',
            'value' => 'premium',
            'logic' => 'and',
            'group' => 0,
            'order' => 0,
        ]);

        WorkflowCondition::create([
            'workflow_id' => $workflow->id,
            'type' => 'field',
            'field' => 'type',
            'operator' => 'equals',
            'value' => 'enterprise',
            'logic' => 'or',
            'group' => 0,
            'order' => 1,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => ['handler' => 'or_condition_action'],
            'order' => 0,
        ]);

        // First OR condition met
        $context = new WorkflowContext(['type' => 'premium']);
        $result = Workflows::execute($workflow->id, $context);
        $this->assertTrue($result->isSuccess());

        // Second OR condition met
        $context = new WorkflowContext(['type' => 'enterprise']);
        $result = Workflows::execute($workflow->id, $context);
        $this->assertTrue($result->isSuccess());

        // Neither condition met
        $context = new WorkflowContext(['type' => 'basic']);
        $result = Workflows::execute($workflow->id, $context);
        $this->assertTrue($result->isSkipped());
    }

    public function test_it_executes_multiple_actions_in_order(): void
    {
        $order = [];

        CustomAction::register('action_1', function () use (&$order) {
            $order[] = 'action_1';

            return ActionResult::success();
        });

        CustomAction::register('action_2', function () use (&$order) {
            $order[] = 'action_2';

            return ActionResult::success();
        });

        CustomAction::register('action_3', function () use (&$order) {
            $order[] = 'action_3';

            return ActionResult::success();
        });

        $workflow = Workflow::create([
            'name' => 'Multi Action Workflow',
            'is_active' => true,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => ['handler' => 'action_1'],
            'order' => 0,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => ['handler' => 'action_2'],
            'order' => 1,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => ['handler' => 'action_3'],
            'order' => 2,
        ]);

        $context = new WorkflowContext([]);
        $result = Workflows::execute($workflow->id, $context);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(3, $result->actionResults);
        $this->assertEquals(['action_1', 'action_2', 'action_3'], $order);
    }

    public function test_it_continues_on_failure_when_configured(): void
    {
        CustomAction::register('failing_action', fn () => ActionResult::failure('This action fails'));
        CustomAction::register('after_failure_action', fn () => ActionResult::success('This should still run'));

        $workflow = Workflow::create([
            'name' => 'Continue on Failure Workflow',
            'is_active' => true,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => ['handler' => 'failing_action'],
            'order' => 0,
            'continue_on_failure' => true,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => ['handler' => 'after_failure_action'],
            'order' => 1,
        ]);

        $context = new WorkflowContext([]);
        $result = Workflows::execute($workflow->id, $context);

        $this->assertFalse($result->isSuccess()); // Overall fails because one action failed
        $this->assertCount(2, $result->actionResults);
        $this->assertFalse($result->actionResults[0]->isSuccess());
        $this->assertTrue($result->actionResults[1]->isSuccess());
    }

    public function test_it_stops_on_failure_when_configured(): void
    {
        $secondActionExecuted = false;

        CustomAction::register('stopping_action', fn () => ActionResult::failure('This action fails'));
        CustomAction::register('should_not_run', function () use (&$secondActionExecuted) {
            $secondActionExecuted = true;

            return ActionResult::success();
        });

        $workflow = Workflow::create([
            'name' => 'Stop on Failure Workflow',
            'is_active' => true,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => ['handler' => 'stopping_action'],
            'order' => 0,
            'continue_on_failure' => false,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => ['handler' => 'should_not_run'],
            'order' => 1,
        ]);

        $context = new WorkflowContext([]);
        $result = Workflows::execute($workflow->id, $context);

        $this->assertFalse($result->isSuccess());
        $this->assertCount(1, $result->actionResults);
        $this->assertFalse($secondActionExecuted);
    }

    public function test_it_interpolates_variables_in_action_config(): void
    {
        $receivedConfig = null;

        CustomAction::register('interpolation_action', function (WorkflowContext $context, array $params) use (&$receivedConfig) {
            $receivedConfig = $params;

            return ActionResult::success();
        });

        $workflow = Workflow::create([
            'name' => 'Interpolation Workflow',
            'is_active' => true,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => [
                'handler' => 'interpolation_action',
                'params' => [
                    'message' => 'Hello {{ user.name }}!',
                    'email' => '{{ user.email }}',
                ],
            ],
            'order' => 0,
        ]);

        $context = new WorkflowContext([
            'user' => [
                'name' => 'John',
                'email' => 'john@example.com',
            ],
        ]);

        Workflows::execute($workflow->id, $context);

        $this->assertEquals('Hello John!', $receivedConfig['message']);
        $this->assertEquals('john@example.com', $receivedConfig['email']);
    }

    public function test_it_does_not_execute_inactive_workflow(): void
    {
        $this->expectException(\Pstoute\LaravelWorkflows\Exceptions\WorkflowException::class);

        $workflow = Workflow::create([
            'name' => 'Inactive Workflow',
            'is_active' => false,
        ]);

        Workflows::execute($workflow->id, new WorkflowContext([]));
    }

    public function test_it_creates_execution_logs(): void
    {
        CustomAction::register('logged_action', fn () => ActionResult::success('Logged', ['data' => 'test']));

        $workflow = Workflow::create([
            'name' => 'Logged Workflow',
            'is_active' => true,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'type' => 'custom',
            'configuration' => ['handler' => 'logged_action'],
            'order' => 0,
        ]);

        $context = new WorkflowContext(['input' => 'value']);
        Workflows::execute($workflow->id, $context);

        $execution = WorkflowExecution::where('workflow_id', $workflow->id)->first();

        $this->assertNotNull($execution);
        $this->assertEquals('completed', $execution->status);
        $this->assertNotNull($execution->started_at);
        $this->assertNotNull($execution->completed_at);

        $logs = $execution->logs;
        $this->assertGreaterThanOrEqual(1, $logs->count());
    }
}
