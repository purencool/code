<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Templates\LaravelTemplate;
use PHPUnit\Framework\TestCase;

class LaravelTemplateTest extends TestCase
{
    private LaravelTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->template = new LaravelTemplate();
    }

    public function test_it_returns_correct_stack_structure(): void
    {
        $stack = $this->template->getStack();

        $this->assertArrayHasKey('primary_language', $stack);
        $this->assertEquals('php', $stack['primary_language']);
        $this->assertArrayHasKey('frameworks', $stack);
        $this->assertArrayHasKey('laravel', $stack['frameworks']);
        $this->assertArrayHasKey('templating_and_styling', $stack);
        $this->assertArrayHasKey('blade', $stack['templating_and_styling']);
    }

    public function test_it_returns_correct_agent_rules(): void
    {
        $rules = $this->template->getAgentRules();

        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
        $this->assertStringContainsString('SYSTEM ROLE', $rules[0]);
    }
}
