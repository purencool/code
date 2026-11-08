<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AgentPathService;
use App\Services\WorkspaceManager;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WorkspaceManagerTest extends TestCase
{
    private string $tempDir;
    private AgentPathService $paths;
    private WorkspaceManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/pnc_ws_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->paths = new AgentPathService($this->tempDir);
        $this->manager = new WorkspaceManager($this->paths);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_it_secures_env_file_in_gitignore_when_missing(): void
    {
        $gitignore = $this->tempDir . '/.gitignore';
        $envRel = $this->paths->getRelativeEnvPath();

        $result = $this->manager->secureEnvFile($gitignore);
        $this->assertTrue($result);
        $this->assertFileExists($gitignore);

        $content = (string) File::get($gitignore);
        $this->assertStringContainsString($envRel, $content);
    }

    public function test_it_does_not_duplicate_env_in_gitignore_when_present(): void
    {
        $gitignore = $this->tempDir . '/.gitignore';
        $envRel = $this->paths->getRelativeEnvPath();
        File::put($gitignore, "node_modules\n{$envRel}\nvendor\n");

        $result = $this->manager->secureEnvFile($gitignore);
        $this->assertFalse($result);

        $content = (string) File::get($gitignore);
        $this->assertSame(1, substr_count($content, $envRel));
    }
}
