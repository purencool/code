<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Code;

use App\Services\Code\ImplementationPromptBuilder;
use Tests\TestCase;

/**
 * @covers \App\Services\Code\ImplementationPromptBuilder
 */
class ImplementationPromptBuilderTest extends TestCase
{
    private ImplementationPromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ImplementationPromptBuilder();
    }

    public function test_builds_step_three_implementation_prompt(): void
    {
        $task = "Target Class: app/Services/Chat/ClientService.php\nDependencies: App\Agents\ChatAgent\nTask: Pass tests.";
        $plan = [
            'test_files' => [
                ['path' => 'tests/Unit/Services/Chat/ClientServiceTest.php', 'code' => 'public function test_chat() {}'],
            ],
        ];

        $prompt = $this->builder->build($task, $plan, ['agent_rules' => ['Clean architecture']]);

        $this->assertStringContainsString('Step 3 (Production Implementation) of TDD', $prompt);
        $this->assertStringContainsString('Class Name: ClientService', $prompt);
        $this->assertStringContainsString('public function test_chat() {}', $prompt);
        $this->assertStringContainsString('- App\Agents\ChatAgent', $prompt);
        $this->assertStringContainsString('FORBIDDEN CONSTRUCTS', $prompt);
    }
}
