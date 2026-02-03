<?php

declare(strict_types=1);

namespace Pstoute\WorkflowConductor\Tests\Unit;

use Pstoute\WorkflowConductor\Data\WorkflowContext;
use Pstoute\WorkflowConductor\Support\VariableInterpolator;
use Pstoute\WorkflowConductor\Tests\TestCase;

class VariableInterpolatorTest extends TestCase
{
    protected VariableInterpolator $interpolator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->interpolator = new VariableInterpolator();
    }

    public function test_it_interpolates_simple_variables(): void
    {
        $context = new WorkflowContext(['name' => 'John']);
        $result = $this->interpolator->interpolate('Hello {{ name }}!', $context);

        $this->assertEquals('Hello John!', $result);
    }

    public function test_it_interpolates_nested_variables(): void
    {
        $context = new WorkflowContext([
            'user' => [
                'name' => 'John',
                'email' => 'john@example.com',
            ],
        ]);

        $result = $this->interpolator->interpolate('{{ user.name }} <{{ user.email }}>', $context);

        $this->assertEquals('John <john@example.com>', $result);
    }

    public function test_it_returns_non_string_values_for_single_variable(): void
    {
        $context = new WorkflowContext(['count' => 42]);
        $result = $this->interpolator->interpolate('{{ count }}', $context);

        $this->assertSame(42, $result);
    }

    public function test_it_applies_uppercase_filter(): void
    {
        $context = new WorkflowContext(['name' => 'john']);
        $result = $this->interpolator->interpolate('{{ name | uppercase }}', $context);

        $this->assertEquals('JOHN', $result);
    }

    public function test_it_applies_lowercase_filter(): void
    {
        $context = new WorkflowContext(['name' => 'JOHN']);
        $result = $this->interpolator->interpolate('{{ name | lowercase }}', $context);

        $this->assertEquals('john', $result);
    }

    public function test_it_applies_number_format_filter(): void
    {
        $context = new WorkflowContext(['amount' => 1234.5678]);
        $result = $this->interpolator->interpolate('${{ amount | number_format:2 }}', $context);

        $this->assertEquals('$1,234.57', $result);
    }

    public function test_it_applies_date_filter(): void
    {
        $context = new WorkflowContext(['date' => '2024-01-15 10:30:00']);
        $result = $this->interpolator->interpolate('{{ date | date:Y-m-d }}', $context);

        $this->assertEquals('2024-01-15', $result);
    }

    public function test_it_applies_default_filter(): void
    {
        $context = new WorkflowContext(['name' => null]);
        $result = $this->interpolator->interpolate('{{ name | default:Guest }}', $context);

        $this->assertEquals('Guest', $result);
    }

    public function test_it_applies_json_filter(): void
    {
        $context = new WorkflowContext(['data' => ['a' => 1, 'b' => 2]]);
        $result = $this->interpolator->interpolate('{{ data | json }}', $context);

        $this->assertEquals('{"a":1,"b":2}', $result);
    }

    public function test_it_interpolates_arrays_recursively(): void
    {
        $context = new WorkflowContext(['name' => 'John', 'email' => 'john@example.com']);

        $input = [
            'to' => '{{ email }}',
            'subject' => 'Hello {{ name }}',
            'data' => [
                'recipient' => '{{ name }}',
            ],
        ];

        $result = $this->interpolator->interpolate($input, $context);

        $this->assertEquals([
            'to' => 'john@example.com',
            'subject' => 'Hello John',
            'data' => [
                'recipient' => 'John',
            ],
        ], $result);
    }

    public function test_it_handles_missing_variables(): void
    {
        $context = new WorkflowContext([]);
        $result = $this->interpolator->interpolate('{{ missing }}', $context);

        $this->assertNull($result);
    }

    public function test_it_handles_missing_variables_in_string(): void
    {
        $context = new WorkflowContext(['name' => 'John']);
        $result = $this->interpolator->interpolate('Hello {{ name }}, your id is {{ id }}', $context);

        $this->assertEquals('Hello John, your id is ', $result);
    }

    public function test_it_applies_money_filter(): void
    {
        $context = new WorkflowContext(['price' => 99.99]);
        $result = $this->interpolator->interpolate('{{ price | money }}', $context);

        $this->assertEquals('$99.99', $result);
    }

    public function test_it_applies_slug_filter(): void
    {
        $context = new WorkflowContext(['title' => 'Hello World!']);
        $result = $this->interpolator->interpolate('{{ title | slug }}', $context);

        $this->assertEquals('hello-world', $result);
    }

    public function test_it_applies_count_filter(): void
    {
        $context = new WorkflowContext(['items' => [1, 2, 3, 4, 5]]);
        $result = $this->interpolator->interpolate('{{ items | count }}', $context);

        $this->assertEquals(5, $result);
    }

    public function test_it_applies_join_filter(): void
    {
        $context = new WorkflowContext(['tags' => ['php', 'laravel', 'workflow']]);
        $result = $this->interpolator->interpolate('{{ tags | join:, }}', $context);

        $this->assertEquals('php, laravel, workflow', $result);
    }

    public function test_it_applies_round_filter(): void
    {
        $context = new WorkflowContext(['value' => 3.14159]);
        $result = $this->interpolator->interpolate('{{ value | round:2 }}', $context);

        $this->assertEquals(3.14, $result);
    }
}
