<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AgentContextService;
use App\Services\AgentPathService;
use App\Services\CompletionService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CompletionServiceTest extends TestCase
{
    private string $tempDir;
    private AgentPathService $paths;
    private AgentContextService $context;
    private CompletionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/pnc_completion_test_' . uniqid();
        $this->paths = new AgentPathService($this->tempDir);
        $this->context = new AgentContextService($this->tempDir, $this->paths);
        $this->service = new CompletionService($this->context);

        mkdir($this->paths->getLogsDir(), 0777, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_it_returns_correct_log_file_path(): void
    {
        $expectedLog = $this->paths->getLogsDir(sprintf('code-%s.log', date('Y-m-d')));
        $this->assertSame($expectedLog, $this->service->getLogFile());
    }

    public function test_it_logs_completion_to_file(): void
    {
        $this->service->logCompletion('def add(', 'a, b): return a + b');

        $logFile = $this->service->getLogFile();
        $this->assertFileExists($logFile);

        $content = (string) File::get($logFile);
        $this->assertStringContainsString('PROMPT: def add(', $content);
        $this->assertStringContainsString('COMPLETION: a, b): return a + b', $content);
    }
}
