<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Code\Commands;

use App\Services\Code\TaskInputReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * @covers \App\Services\Code\TaskInputReader
 */
class TaskInputReaderTest extends TestCase
{
    private TaskInputReader $reader;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new TaskInputReader();
        $this->tempDir = sys_get_temp_dir() . '/task_reader_test_' . uniqid();
        File::ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_resolves_task_from_workspace_file(): void
    {
        $taskFile = $this->tempDir . '/tasks.md';
        File::put($taskFile, 'Target Type: TDD');

        $command = $this->createMock(Command::class);
        $result = $this->reader->resolve($command, $this->tempDir, 'tasks.md', false);

        $this->assertSame('Target Type: TDD', $result);
    }

    public function test_resolves_direct_argument_string_when_not_a_file(): void
    {
        $command = $this->createMock(Command::class);
        $result = $this->reader->resolve($command, $this->tempDir, 'Target Type: CCC', false);

        $this->assertSame('Target Type: CCC', $result);
    }
}
