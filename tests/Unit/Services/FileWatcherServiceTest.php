<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AgentPathService;
use App\Services\FileWatcherService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FileWatcherServiceTest extends TestCase
{
    private string $tempDir;
    private AgentPathService $paths;
    private FileWatcherService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/pnc_watcher_test_' . uniqid();
        $this->paths = new AgentPathService($this->tempDir);
        $this->service = new FileWatcherService($this->paths);

        mkdir($this->paths->getAgentDir(), 0777, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_it_ignores_agent_directory_and_standard_exclusions(): void
    {
        $this->assertTrue($this->service->shouldIgnore($this->paths->getRelativePath('config.json')));
        $this->assertTrue($this->service->shouldIgnore('vendor/autoload.php'));
        $this->assertTrue($this->service->shouldIgnore('node_modules/package.json'));
        $this->assertTrue($this->service->shouldIgnore('.git/HEAD'));
        $this->assertFalse($this->service->shouldIgnore('app/Models/User.php'));
    }

    public function test_it_logs_events_to_watch_log(): void
    {
        $this->service->logEvent('modify', 'app/Services/UserService.php');

        $logFile = $this->paths->getWatchLogPath();
        $this->assertFileExists($logFile);

        $content = (string) File::get($logFile);
        $this->assertStringContainsString('MODIFY: app/Services/UserService.php', $content);
    }

    public function test_it_records_and_checks_pid(): void
    {
        $pidFile = $this->paths->getPidPath();
        $this->assertFalse($this->service->isRunning());

        $this->service->recordPid(999999);
        $this->assertFileExists($pidFile);
        $this->assertSame('999999', trim((string) File::get($pidFile)));
    }

    public function test_it_stops_watcher(): void
    {
        $this->service->recordPid(123456);
        $this->assertTrue($this->service->stop());
        $this->assertFileDoesNotExist($this->paths->getPidPath());
    }
}
