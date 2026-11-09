<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Code\Commands;

use App\Services\Code\Commands\TddPromptBuilder;
use Tests\TestCase;

/**
 * @covers \App\Services\Code\TddPromptBuilder
 */
class TddPromptBuilderTest extends TestCase
{
    private TddPromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new TddPromptBuilder();
    }

    public function test_delegates_prompt_building_and_parsing_methods(): void
    {
        $task = "Target Test: tests/Unit/DemoTest.php\nTarget Class: app/Demo.php\nTask: Delegate check.";

        $genPrompt = $this->builder->buildTestGenerationPrompt($task, 'ctx', []);
        $this->assertStringContainsString('Step 1 (Test Generation)', $genPrompt);

        $cttPrompt = $this->builder->buildCttTestPrompt($task, '<?php class Demo {}', []);
        $this->assertStringContainsString('Code-To-Test characterization', $cttPrompt);

        $meta = $this->builder->parseTaskMetadata($task);
        $this->assertSame('app/Demo.php', $meta['target_class']);

        $this->assertSame('App', $this->builder->resolveNamespaceFromPath('app/Demo.php', 'Fallback'));
        $this->assertSame('Demo', $this->builder->resolveClassNameFromPath('app/Demo.php', 'Fallback'));
    }
}
