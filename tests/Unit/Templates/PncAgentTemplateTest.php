<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Templates\PncAgentTemplate;
use PHPUnit\Framework\TestCase;

class PncAgentTemplateTest extends TestCase
{
    private PncAgentTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->template = new PncAgentTemplate();
    }

    public function test_it_returns_correct_stack_structure(): void
    {
        $stack = $this->template->getStack();

        $this->assertEquals('php', $stack['primary_language']);
        $this->assertArrayHasKey('laravel-zero', $stack['frameworks']);
        $this->assertArrayHasKey('neuron-ai', $stack['frameworks']);
        $this->assertEmpty($stack['templating_and_styling']);
    }

    public function test_it_returns_correct_agent_rules(): void
    {
        $rules = $this->template->getAgentRules();

        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
        $this->assertStringContainsString('Laravel Zero, and Neuron AI', $rules[0]);
    }
}
