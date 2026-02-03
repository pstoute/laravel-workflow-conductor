<?php

declare(strict_types=1);

namespace Pstoute\LaravelWorkflows\Tests\Unit;

use Pstoute\LaravelWorkflows\Engine\ConditionEvaluator;
use Pstoute\LaravelWorkflows\Tests\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    protected ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new ConditionEvaluator();
    }

    public function test_equals_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('equals', 'hello', 'hello'));
        $this->assertTrue($this->evaluator->evaluateOperator('equals', 'Hello', 'hello')); // case insensitive
        $this->assertTrue($this->evaluator->evaluateOperator('equals', 42, 42));
        $this->assertTrue($this->evaluator->evaluateOperator('equals', '42', 42)); // numeric comparison
        $this->assertFalse($this->evaluator->evaluateOperator('equals', 'hello', 'world'));
    }

    public function test_not_equals_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('not_equals', 'hello', 'world'));
        $this->assertFalse($this->evaluator->evaluateOperator('not_equals', 'hello', 'hello'));
    }

    public function test_contains_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('contains', 'hello world', 'world'));
        $this->assertTrue($this->evaluator->evaluateOperator('contains', 'Hello World', 'world')); // case insensitive
        $this->assertTrue($this->evaluator->evaluateOperator('contains', ['a', 'b', 'c'], 'b'));
        $this->assertFalse($this->evaluator->evaluateOperator('contains', 'hello', 'world'));
    }

    public function test_not_contains_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('not_contains', 'hello', 'world'));
        $this->assertFalse($this->evaluator->evaluateOperator('not_contains', 'hello world', 'world'));
    }

    public function test_starts_with_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('starts_with', 'hello world', 'hello'));
        $this->assertTrue($this->evaluator->evaluateOperator('starts_with', 'Hello World', 'hello')); // case insensitive
        $this->assertFalse($this->evaluator->evaluateOperator('starts_with', 'hello world', 'world'));
    }

    public function test_ends_with_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('ends_with', 'hello world', 'world'));
        $this->assertTrue($this->evaluator->evaluateOperator('ends_with', 'Hello World', 'world')); // case insensitive
        $this->assertFalse($this->evaluator->evaluateOperator('ends_with', 'hello world', 'hello'));
    }

    public function test_greater_than_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('greater_than', 10, 5));
        $this->assertTrue($this->evaluator->evaluateOperator('greater_than', 10.5, 10));
        $this->assertFalse($this->evaluator->evaluateOperator('greater_than', 5, 10));
        $this->assertFalse($this->evaluator->evaluateOperator('greater_than', 10, 10));
    }

    public function test_greater_or_equal_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('greater_or_equal', 10, 5));
        $this->assertTrue($this->evaluator->evaluateOperator('greater_or_equal', 10, 10));
        $this->assertFalse($this->evaluator->evaluateOperator('greater_or_equal', 5, 10));
    }

    public function test_less_than_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('less_than', 5, 10));
        $this->assertFalse($this->evaluator->evaluateOperator('less_than', 10, 5));
        $this->assertFalse($this->evaluator->evaluateOperator('less_than', 10, 10));
    }

    public function test_less_or_equal_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('less_or_equal', 5, 10));
        $this->assertTrue($this->evaluator->evaluateOperator('less_or_equal', 10, 10));
        $this->assertFalse($this->evaluator->evaluateOperator('less_or_equal', 10, 5));
    }

    public function test_is_null_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('is_null', null, null));
        $this->assertFalse($this->evaluator->evaluateOperator('is_null', 'value', null));
    }

    public function test_is_not_null_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('is_not_null', 'value', null));
        $this->assertFalse($this->evaluator->evaluateOperator('is_not_null', null, null));
    }

    public function test_is_empty_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('is_empty', '', null));
        $this->assertTrue($this->evaluator->evaluateOperator('is_empty', [], null));
        $this->assertTrue($this->evaluator->evaluateOperator('is_empty', null, null));
        $this->assertTrue($this->evaluator->evaluateOperator('is_empty', '   ', null)); // whitespace
        $this->assertFalse($this->evaluator->evaluateOperator('is_empty', 'value', null));
        $this->assertFalse($this->evaluator->evaluateOperator('is_empty', ['item'], null));
    }

    public function test_is_not_empty_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('is_not_empty', 'value', null));
        $this->assertTrue($this->evaluator->evaluateOperator('is_not_empty', ['item'], null));
        $this->assertFalse($this->evaluator->evaluateOperator('is_not_empty', '', null));
        $this->assertFalse($this->evaluator->evaluateOperator('is_not_empty', [], null));
    }

    public function test_in_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('in', 'b', ['a', 'b', 'c']));
        $this->assertTrue($this->evaluator->evaluateOperator('in', 2, [1, 2, 3]));
        $this->assertFalse($this->evaluator->evaluateOperator('in', 'd', ['a', 'b', 'c']));
    }

    public function test_not_in_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('not_in', 'd', ['a', 'b', 'c']));
        $this->assertFalse($this->evaluator->evaluateOperator('not_in', 'b', ['a', 'b', 'c']));
    }

    public function test_matches_regex_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('matches_regex', 'hello123', '\d+'));
        $this->assertTrue($this->evaluator->evaluateOperator('matches_regex', 'test@example.com', '/^[\w.-]+@[\w.-]+\.\w+$/'));
        $this->assertFalse($this->evaluator->evaluateOperator('matches_regex', 'hello', '\d+'));
    }

    public function test_is_true_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('is_true', true, null));
        $this->assertTrue($this->evaluator->evaluateOperator('is_true', 1, null));
        $this->assertTrue($this->evaluator->evaluateOperator('is_true', 'yes', null));
        $this->assertFalse($this->evaluator->evaluateOperator('is_true', false, null));
        $this->assertFalse($this->evaluator->evaluateOperator('is_true', 0, null));
    }

    public function test_is_false_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('is_false', false, null));
        $this->assertTrue($this->evaluator->evaluateOperator('is_false', 0, null));
        $this->assertFalse($this->evaluator->evaluateOperator('is_false', true, null));
    }

    public function test_between_operator(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('between', 5, [1, 10]));
        $this->assertTrue($this->evaluator->evaluateOperator('between', 1, [1, 10])); // inclusive
        $this->assertTrue($this->evaluator->evaluateOperator('between', 10, [1, 10])); // inclusive
        $this->assertFalse($this->evaluator->evaluateOperator('between', 0, [1, 10]));
        $this->assertFalse($this->evaluator->evaluateOperator('between', 11, [1, 10]));
    }

    public function test_operator_aliases(): void
    {
        $this->assertTrue($this->evaluator->evaluateOperator('eq', 'test', 'test'));
        $this->assertTrue($this->evaluator->evaluateOperator('=', 'test', 'test'));
        $this->assertTrue($this->evaluator->evaluateOperator('==', 'test', 'test'));
        $this->assertTrue($this->evaluator->evaluateOperator('neq', 'a', 'b'));
        $this->assertTrue($this->evaluator->evaluateOperator('!=', 'a', 'b'));
        $this->assertTrue($this->evaluator->evaluateOperator('<>', 'a', 'b'));
        $this->assertTrue($this->evaluator->evaluateOperator('gt', 10, 5));
        $this->assertTrue($this->evaluator->evaluateOperator('>', 10, 5));
        $this->assertTrue($this->evaluator->evaluateOperator('lt', 5, 10));
        $this->assertTrue($this->evaluator->evaluateOperator('<', 5, 10));
    }
}
