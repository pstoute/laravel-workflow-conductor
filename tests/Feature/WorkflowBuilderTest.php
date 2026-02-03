<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Tests\Feature;

use Pstoute\WorkflowConductor\Facades\Conductor;
use Pstoute\WorkflowConductor\Models\Workflow;
use Pstoute\WorkflowConductor\Tests\TestCase;

class WorkflowBuilderTest extends TestCase
{
    public function test_it_creates_workflow_with_fluent_builder(): void
    {
        $workflow = Conductor::create()
            ->name('Welcome Email')
            ->description('Send welcome email to new users')
            ->trigger('model.created', ['model' => 'App\\Models\\User'])
            ->condition('field', [
                'field' => 'email_verified_at',
                'operator' => 'is_not_null',
            ])
            ->action('send_email', [
                'to' => '{{ model.email }}',
                'subject' => 'Welcome!',
                'body' => 'Hello {{ model.name }}!',
            ])
            ->save();

        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertEquals('Welcome Email', $workflow->name);
        $this->assertEquals('Send welcome email to new users', $workflow->description);
        $this->assertTrue($workflow->is_active);

        $this->assertCount(1, $workflow->triggers);
        $this->assertEquals('model.created', $workflow->triggers->first()->type);

        $this->assertCount(1, $workflow->conditions);
        $this->assertEquals('field', $workflow->conditions->first()->type);
        $this->assertEquals('email_verified_at', $workflow->conditions->first()->field);

        $this->assertCount(1, $workflow->actions);
        $this->assertEquals('send_email', $workflow->actions->first()->type);
    }

    public function test_it_creates_workflow_with_multiple_conditions(): void
    {
        $workflow = Conductor::create()
            ->name('High Value Order Alert')
            ->trigger('model.created', ['model' => 'App\\Models\\Order'])
            ->when('total', 'greater_than', 1000)
            ->when('status', 'equals', 'paid')
            ->action('slack', [
                'message' => 'High value order received!',
            ])
            ->save();

        $this->assertCount(2, $workflow->conditions);
        $this->assertEquals('and', $workflow->conditions[0]->logic);
        $this->assertEquals('and', $workflow->conditions[1]->logic);
    }

    public function test_it_creates_workflow_with_or_conditions(): void
    {
        $workflow = Conductor::create()
            ->name('VIP Customer Alert')
            ->trigger('model.created', ['model' => 'App\\Models\\Order'])
            ->when('customer.type', 'equals', 'vip')
            ->orWhen('customer.type', 'equals', 'enterprise')
            ->action('send_notification', [
                'notification' => 'App\\Notifications\\VipOrderNotification',
            ])
            ->save();

        $this->assertCount(2, $workflow->conditions);
        $this->assertEquals('and', $workflow->conditions[0]->logic);
        $this->assertEquals('or', $workflow->conditions[1]->logic);
    }

    public function test_it_creates_workflow_with_multiple_actions(): void
    {
        $workflow = Conductor::create()
            ->name('Multi Action Workflow')
            ->trigger('model.created', ['model' => 'App\\Models\\Lead'])
            ->sendEmail('{{ model.email }}', 'Welcome!', 'Thanks for signing up!')
            ->slack('New lead: {{ model.name }}')
            ->webhook('https://api.example.com/leads', ['lead_id' => '{{ model.id }}'])
            ->save();

        $this->assertCount(3, $workflow->actions);
        $this->assertEquals('send_email', $workflow->actions[0]->type);
        $this->assertEquals('slack', $workflow->actions[1]->type);
        $this->assertEquals('webhook', $workflow->actions[2]->type);
    }

    public function test_it_creates_workflow_with_delay_action(): void
    {
        $workflow = Conductor::create()
            ->name('Follow Up Email')
            ->trigger('model.created', ['model' => 'App\\Models\\Lead'])
            ->delay(3, 'days')
            ->sendEmail('{{ model.email }}', 'Checking in', 'Just wanted to follow up...')
            ->save();

        $this->assertCount(2, $workflow->actions);
        $this->assertEquals('delay', $workflow->actions[0]->type);
        $this->assertEquals(3, $workflow->actions[0]->configuration['duration']);
        $this->assertEquals('days', $workflow->actions[0]->configuration['unit']);
    }

    public function test_it_creates_inactive_workflow(): void
    {
        $workflow = Conductor::create()
            ->name('Inactive Workflow')
            ->active(false)
            ->trigger('manual')
            ->action('custom', ['handler' => 'test'])
            ->save();

        $this->assertFalse($workflow->is_active);
    }

    public function test_it_creates_workflow_with_settings(): void
    {
        $workflow = Conductor::create()
            ->name('Workflow with Settings')
            ->settings([
                'max_retries' => 5,
                'custom_option' => 'value',
            ])
            ->trigger('manual')
            ->action('custom', ['handler' => 'test'])
            ->save();

        $this->assertEquals(5, $workflow->getSetting('max_retries'));
        $this->assertEquals('value', $workflow->getSetting('custom_option'));
    }

    public function test_it_creates_workflow_with_model_actions(): void
    {
        $workflow = Conductor::create()
            ->name('Order Processing')
            ->trigger('model.created', ['model' => 'App\\Models\\Order'])
            ->createModel('App\\Models\\Invoice', [
                'order_id' => '{{ model.id }}',
                'total' => '{{ model.total }}',
            ])
            ->updateModel([
                'status' => 'processing',
            ])
            ->save();

        $this->assertCount(2, $workflow->actions);
        $this->assertEquals('create_model', $workflow->actions[0]->type);
        $this->assertEquals('update_model', $workflow->actions[1]->type);
    }

    public function test_it_sets_action_order_correctly(): void
    {
        $workflow = Conductor::create()
            ->name('Ordered Actions')
            ->trigger('manual')
            ->action('custom', ['handler' => 'first'])
            ->action('custom', ['handler' => 'second'])
            ->action('custom', ['handler' => 'third'])
            ->save();

        $this->assertEquals(0, $workflow->actions[0]->order);
        $this->assertEquals(1, $workflow->actions[1]->order);
        $this->assertEquals(2, $workflow->actions[2]->order);
    }
}
