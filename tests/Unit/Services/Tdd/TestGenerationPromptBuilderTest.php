<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tdd;

use App\Services\Tdd\TestGenerationPromptBuilder;
use Tests\TestCase;

/**
 * @covers \App\Services\Tdd\TestGenerationPromptBuilder
 */
class TestGenerationPromptBuilderTest extends TestCase
{
    private TestGenerationPromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new TestGenerationPromptBuilder();
    }

    public function test_builds_step_one_test_prompt(): void
    {
        $task = <<<MARKDOWN
Target Test: tests/Unit/Services/Chat/ClientServiceTest.php
Target Class: app/Services/Chat/ClientService.php
Dependencies: App\Agents\ChatAgent
Task:
- Handle chat orchestrations.
MARKDOWN;

        $prompt = $this->builder->build($task, 'Wiring Context Here', ['agent_rules' => ['Rule 1']]);

        $this->assertStringContainsString('Step 1 (Test Generation) of TDD', $prompt);
        $this->assertStringContainsString('Target Test File: tests/Unit/Services/Chat/ClientServiceTest.php', $prompt);
        $this->assertStringContainsString('Target Production Class: App\Services\Chat\ClientService', $prompt);
        $this->assertStringContainsString('- App\Agents\ChatAgent', $prompt);
        $this->assertStringContainsString('- Rule 1', $prompt);
        $this->assertStringContainsString('Wiring Context Here', $prompt);
    }
}
