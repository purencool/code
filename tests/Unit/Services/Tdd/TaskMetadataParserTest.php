<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tdd;

use App\Services\Tdd\TaskMetadataParser;
use RuntimeException;
use Tests\TestCase;

/**
 * @covers \App\Services\Tdd\TaskMetadataParser
 */
class TaskMetadataParserTest extends TestCase
{
    private TaskMetadataParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TaskMetadataParser();
    }

    public function test_parses_target_classes_dependencies_and_tasks(): void
    {
        $markdown = <<<MARKDOWN
Target Type: TDD
Target Test: tests/Unit/Services/Chat/ClientLoggerTest.php
Target Class: app/Services/Chat/ClientLogger.php
Dependencies: App\Services\AgentContextService, Illuminate\Support\Facades\File
Task:
- Write chat logs to .agent/environment/chatdate-client.log.
MARKDOWN;

        $meta = $this->parser->parse($markdown);

        $this->assertSame('TDD', $meta['target_type']);
        $this->assertSame('tests/Unit/Services/Chat/ClientLoggerTest.php', $meta['target_test']);
        $this->assertSame('app/Services/Chat/ClientLogger.php', $meta['target_class']);
        $this->assertCount(2, $meta['dependencies']);
        $this->assertStringContainsString('Write chat logs', $meta['task']);
    }

    public function test_infers_test_path_from_target_class(): void
    {
        $meta = $this->parser->parse("Target Class: app/Commands/ClientCommand.php\nTask: Test");
        $this->assertSame('tests/Feature/Commands/ClientCommandTest.php', $meta['target_test']);

        $metaUnit = $this->parser->parse("Target Class: app/Services/Chat/ClientService.php\nTask: Test");
        $this->assertSame('tests/Unit/Services/Chat/ClientServiceTest.php', $metaUnit['target_test']);
    }

    public function test_throws_exception_when_targets_are_omitted(): void
    {
        $this->expectException(RuntimeException::class);
        $this->parser->parse("Task:\n- Free-form instructions without class headers.");
    }
}
