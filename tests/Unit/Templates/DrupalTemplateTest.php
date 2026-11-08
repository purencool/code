<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Templates\DrupalTemplate;
use PHPUnit\Framework\TestCase;

class DrupalTemplateTest extends TestCase
{
    private DrupalTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->template = new DrupalTemplate();
    }

    public function test_it_returns_correct_stack_structure(): void
    {
        $stack = $this->template->getStack();

        $this->assertEquals('php', $stack['primary_language']);
        $this->assertArrayHasKey('drupal', $stack['frameworks']);
        $this->assertArrayHasKey('twig', $stack['templating_and_styling']);
    }

    public function test_it_returns_correct_agent_rules(): void
    {
        $rules = $this->template->getAgentRules();

        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
        $this->assertStringContainsString('Drupal core developer', $rules[0]);
    }
}
