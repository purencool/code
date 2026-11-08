<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Chat;

use App\Services\AgentContextService;
use App\Services\AgentPathService;
use App\Services\Chat\ClientLogger;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * @covers \App\Services\Chat\ClientLogger
 */
class ClientLoggerTest extends TestCase
{
    private string $tempWorkspace;
    private ClientLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempWorkspace = sys_get_temp_dir() . '/pncagent_test_' . uniqid();
        File::ensureDirectoryExists($this->tempWorkspace);

        // AgentPathService takes ?string $workspaceRoot as argument #1
        $pathService = new AgentPathService($this->tempWorkspace);

        $contextService = $this->createMock(AgentContextService::class);
        $contextService->method('getWorkspaceRoot')->willReturn($this->tempWorkspace);

        $this->logger = new ClientLogger($contextService, $pathService);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempWorkspace);
        parent::tearDown();
    }

    public function test_resolves_log_file_and_creates_environment_directory(): void
    {
        $logPath = $this->logger->getLogFilePath();

        $this->assertStringContainsString('.agent/environment/chatdate-client.log', $logPath);
        $this->assertDirectoryExists(dirname($logPath));
    }

    public function test_appends_timestamped_turn_entries(): void
    {
        $this->logger->logTurn('USER', 'Hello AI');
        $this->logger->logTurn('ASSISTANT', 'Hello Developer');

        $contents = $this->logger->readHistory();

        $this->assertStringContainsString('[USER]', $contents);
        $this->assertStringContainsString('Hello AI', $contents);
        $this->assertStringContainsString('[ASSISTANT]', $contents);
        $this->assertStringContainsString('Hello Developer', $contents);
    }

    public function test_clears_log_contents_successfully(): void
    {
        $this->logger->logTurn('USER', 'Transient message');
        $this->logger->clear();

        $this->assertSame('', trim($this->logger->readHistory()));
    }
}
