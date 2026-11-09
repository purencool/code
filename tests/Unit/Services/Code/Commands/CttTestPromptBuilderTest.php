<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Code\Commands;

use App\Services\Code\Commands\CttTestPromptBuilder;
use Tests\TestCase;

/**
 * @covers \App\Services\Code\CttTestPromptBuilder
 */
class CttTestPromptBuilderTest extends TestCase
{
    private CttTestPromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new CttTestPromptBuilder();
    }

    public function test_builds_ctt_characterization_prompt(): void
    {
        $task = "Target Class: app/Services/Chat/ClientLogger.php\nTask: Verify rotation.";
        $source = "<?php class ClientLogger {}";

        $prompt = $this->builder->build($task, $source, ['agent_rules' => ['Strict TDD']]);

        $this->assertStringContainsString('CTT (Code-To-Test characterization testing)', $prompt);
        $this->assertStringContainsString('app/Services/Chat/ClientLogger.php', $prompt);
        $this->assertStringContainsString($source, $prompt);
        $this->assertStringContainsString('Verify rotation.', $prompt);
    }
}
