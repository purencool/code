<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AgentContextService;
use App\Services\AgentPathService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CompleteCommandTest extends TestCase
{
    private string $tempDir;
    private AgentPathService $paths;
    private AgentContextService $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/pnc_complete_cmd_' . uniqid();
        $this->paths = new AgentPathService($this->tempDir);
        $this->context = new AgentContextService($this->tempDir, $this->paths);

        $this->app->instance(AgentPathService::class, $this->paths);
        $this->app->instance(AgentContextService::class, $this->context);

        mkdir($this->paths->getAgentDir(), 0777, true);
        mkdir($this->paths->getTemplateDir(), 0777, true);

        File::put($this->paths->getConfigPath(), (string) json_encode([
            'project_name' => 'CompleteTestApp',
        ]));
        File::put($this->paths->getEnvPath(), "AI_KEY=sk-test-token\nAGENT_TEMPLATE=php\n");
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_it_executes_inline_completion_command(): void
    {
        $this->artisan('complete', [
            'prompt' => 'public function calculateTotal(',
        ])->assertExitCode(0);
    }
}
