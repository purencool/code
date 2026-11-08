<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AgentContextService;
use App\Services\AgentPathService;
use App\Services\CodebaseIndexService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ScanCommandTest extends TestCase
{
    private string $originalCwd;
    private string $tempWorkspace;
    private AgentPathService $paths;
    private AgentContextService $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string) getcwd();
        $this->tempWorkspace = sys_get_temp_dir() . '/pnc_scan_test_' . uniqid();
        mkdir($this->tempWorkspace, 0777, true);
        chdir($this->tempWorkspace);

        $this->paths = new AgentPathService($this->tempWorkspace);
        $this->context = new AgentContextService($this->tempWorkspace, $this->paths);

        $this->app->instance(AgentPathService::class, $this->paths);
        $this->app->instance(AgentContextService::class, $this->context);
        $this->app->instance(CodebaseIndexService::class, new CodebaseIndexService($this->context));

        mkdir($this->paths->getTemplateDir('php'), 0777, true);
        mkdir($this->paths->getCacheDir(template: 'php'), 0777, true);

        File::put($this->paths->getConfigPath('php'), (string) json_encode([
            'project_name' => 'ScanTestApp',
            'stack' => ['primary_language' => 'php'],
        ]));
        File::put($this->paths->getAgentDir('config.json'), (string) json_encode([
            'project_name' => 'ScanTestApp',
            'stack' => ['primary_language' => 'php'],
        ]));

        File::put($this->paths->getEnvPath(), "AGENT_TEMPLATE=php\n");

        mkdir($this->tempWorkspace . '/app', 0777, true);
        File::put($this->tempWorkspace . '/app/Sample.php', "<?php\nnamespace App;\nclass Sample {}\n");
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        if (File::isDirectory($this->tempWorkspace)) {
            File::deleteDirectory($this->tempWorkspace);
        }
        parent::tearDown();
    }

    public function test_it_scans_codebase_and_generates_wiring_cache(): void
    {
        $this->artisan('scan')
            ->expectsOutputToContain('Index built successfully.')
            ->assertExitCode(0);

        $cacheFile = $this->paths->getCacheDir('wiring.json');
        $this->assertFileExists($cacheFile);

        $cached = json_decode((string) File::get($cacheFile), true);
        $this->assertIsArray($cached);
        $this->assertSame($this->tempWorkspace, $cached['workspace']);
    }

    public function test_it_inspects_individual_file_wiring(): void
    {
        $this->artisan('scan', ['file' => 'app/Sample.php'])
            ->expectsOutputToContain('Inspecting wiring for: app/Sample.php')
            ->assertExitCode(0);
    }
}
