<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Templates\NodeJsTemplate;
use PHPUnit\Framework\TestCase;

class NodeJsTemplateTest extends TestCase
{
    private NodeJsTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->template = new NodeJsTemplate();
    }

    public function test_it_returns_correct_stack_structure(): void
    {
        $stack = $this->template->getStack();

        $this->assertEquals('javascript', $stack['primary_language']);
        $this->assertArrayHasKey('express', $stack['frameworks']);
        $this->assertEmpty($stack['templating_and_styling']);
    }

    public function test_it_returns_correct_agent_rules(): void
    {
        $rules = $this->template->getAgentRules();

        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
        $this->assertStringContainsString('Node.js backend developer', $rules[0]);
    }
}
