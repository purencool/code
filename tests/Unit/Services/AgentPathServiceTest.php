<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AgentPathService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AgentPathServiceTest extends TestCase
{
    private string $tempWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempWorkspace = sys_get_temp_dir() . '/pnc_path_test_' . uniqid();
        mkdir($this->tempWorkspace, 0777, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempWorkspace)) {
            File::deleteDirectory($this->tempWorkspace);
        }
        putenv('AGENT_DIR');
        putenv('AGENT_TEMPLATE');
        unset($_ENV['AGENT_DIR'], $_SERVER['AGENT_DIR'], $_ENV['AGENT_TEMPLATE'], $_SERVER['AGENT_TEMPLATE']);
        parent::tearDown();
    }

    public function test_it_defaults_to_standard_agent_directory_and_template(): void
    {
        $service = new AgentPathService($this->tempWorkspace);
        $dir = AgentPathService::DEFAULT_DIRECTORY_NAME;
        $tpl = AgentPathService::DEFAULT_TEMPLATE;

        $this->assertSame($dir, $service->getDirectoryName());
        $this->assertSame($tpl, $service->getActiveTemplate());

        $this->assertSame($this->tempWorkspace . '/' . $dir, $service->getAgentDir());
        $this->assertSame($this->tempWorkspace . '/' . $dir . '/.env', $service->getEnvPath());
        $this->assertSame($dir . '/.env', $service->getRelativeEnvPath());

        // Template scoped paths: (dir)/(active_template)/...
        $this->assertSame($this->tempWorkspace . '/' . $dir . '/' . $tpl . '/watch.log', $service->getWatchLogPath());
        $this->assertSame($dir . '/' . $tpl . '/watch.log', $service->getRelativeWatchLogPath());
        $this->assertSame($this->tempWorkspace . '/' . $dir . '/' . $tpl . '/cache', $service->getCacheDir());
    }

    public function test_it_reads_custom_template_from_agent_env(): void
    {
        $service = new AgentPathService($this->tempWorkspace);
        $dir = AgentPathService::DEFAULT_DIRECTORY_NAME;

        mkdir($service->getAgentDir(), 0777, true);
        File::put($service->getEnvPath(), "AGENT_TEMPLATE=laravel\n");

        $this->assertSame('laravel', $service->getActiveTemplate());
        $this->assertSame($this->tempWorkspace . '/' . $dir . '/laravel/watch.log', $service->getWatchLogPath());
        $this->assertSame($dir . '/laravel/watch.log', $service->getRelativeWatchLogPath());
    }

    public function test_it_reads_custom_directory_name_from_runtime_environment_variable(): void
    {
        putenv('AGENT_DIR=.pnc');
        $_ENV['AGENT_DIR'] = '.pnc';

        $service = new AgentPathService($this->tempWorkspace);

        $this->assertSame('.pnc', $service->getDirectoryName());
        $this->assertSame($this->tempWorkspace . '/.pnc', $service->getAgentDir());
        $this->assertSame($this->tempWorkspace . '/.pnc/.env', $service->getEnvPath());
        $this->assertSame('.pnc/.env', $service->getRelativeEnvPath());
    }

    public function test_it_reads_custom_directory_and_template_from_workspace_dot_env(): void
    {
        File::put($this->tempWorkspace . '/.env', "AGENT_DIR=custom_agent\nAGENT_TEMPLATE=drupal\n");

        $service = new AgentPathService($this->tempWorkspace);

        $this->assertSame('custom_agent', $service->getDirectoryName());
        $this->assertSame('drupal', $service->getActiveTemplate());
        $this->assertSame($this->tempWorkspace . '/custom_agent/drupal/logs', $service->getLogsDir());
        $this->assertSame($this->tempWorkspace . '/custom_agent/drupal/cache/wiring.json', $service->getCacheDir('wiring.json'));
        $this->assertSame('custom_agent/drupal/watch.log', $service->getRelativeWatchLogPath());
    }

    public function test_it_resolves_template_specific_paths_correctly(): void
    {
        $service = new AgentPathService($this->tempWorkspace);
        $dir = AgentPathService::DEFAULT_DIRECTORY_NAME;

        $this->assertSame($this->tempWorkspace . '/' . $dir . '/laravel/config.json', $service->getConfigPath('laravel'));
        $this->assertSame($dir . '/laravel/config.json', $service->getRelativePath('laravel/config.json'));
    }
}
