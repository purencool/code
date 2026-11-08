<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AgentContextService;
use App\Services\AgentPathService;
use App\Services\CodebaseIndexService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CodebaseIndexServiceTest extends TestCase
{
    private string $tempWorkspace;
    private AgentPathService $paths;
    private AgentContextService $context;
    private CodebaseIndexService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempWorkspace = sys_get_temp_dir() . '/pnc_index_test_' . uniqid();
        mkdir($this->tempWorkspace, 0777, true);

        $this->paths = new AgentPathService($this->tempWorkspace);
        mkdir($this->paths->getAgentDir(), 0777, true);

        // Explicitly set the active template via .env
        File::put($this->paths->getEnvPath(), "AGENT_TEMPLATE=laravel\n");

        $this->context = new AgentContextService($this->tempWorkspace, $this->paths);
        $this->service = new CodebaseIndexService($this->context, $this->paths);

        mkdir($this->paths->getTemplateDir(), 0777, true);
        mkdir($this->paths->getCacheDir(), 0777, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempWorkspace)) {
            File::deleteDirectory($this->tempWorkspace);
        }
        parent::tearDown();
    }

    public function test_it_indexes_polyglot_files_and_extracts_wiring(): void
    {
        mkdir($this->tempWorkspace . '/src', 0777, true);

        // 1. PHP Service
        File::put(
            $this->tempWorkspace . '/src/UserService.php',
            "<?php\nnamespace App\Services;\nuse App\Models\User;\nclass UserService {}\n"
        );

        // 2. JavaScript / TypeScript Component
        File::put(
            $this->tempWorkspace . '/src/ApiClient.js',
            "import axios from 'axios';\nclass ApiClient {}\nexport default ApiClient;\n"
        );

        // 3. Python Service
        File::put(
            $this->tempWorkspace . '/src/worker.py',
            "import os\nfrom celery import Celery\nclass WorkerService:\n    pass\n"
        );

        $graph = $this->service->scan($this->tempWorkspace);

        $this->assertArrayHasKey('symbols', $graph);
        $this->assertArrayHasKey('UserService', $graph['symbols']);
        $this->assertArrayHasKey('ApiClient', $graph['symbols']);
        $this->assertArrayHasKey('WorkerService', $graph['symbols']);

        $this->assertContains('App\Models\User', $graph['symbols']['UserService']['dependencies']);
        $this->assertContains('axios', $graph['symbols']['ApiClient']['dependencies']);
        $this->assertContains('celery', $graph['symbols']['WorkerService']['dependencies']);

        // Verify active template was resolved directly from credentials .env
        $this->assertSame('laravel', $this->paths->getActiveTemplate());

        // Cache file must reside inside template-scoped directory: {agent_dir}/laravel/cache/wiring.json
        $cacheFile = $this->paths->getCacheDir('wiring.json');
        $this->assertFileExists($cacheFile);

        $expectedRelPath = $this->paths->getRelativePath('laravel/cache/wiring.json');
        $this->assertSame($expectedRelPath, $this->paths->getRelativeCachePath('wiring.json'));
    }

    public function test_it_switches_cache_destination_when_agent_template_changes_in_env(): void
    {
        // Change template in credentials .env to 'nodejs'
        File::put($this->paths->getEnvPath(), "AGENT_TEMPLATE=nodejs\n");

        $paths = new AgentPathService($this->tempWorkspace);
        $context = new AgentContextService($this->tempWorkspace, $paths);
        $service = new CodebaseIndexService($context, $paths);

        mkdir($this->tempWorkspace . '/src', 0777, true);
        File::put($this->tempWorkspace . '/src/App.js', "class App {}\n");

        $service->scan($this->tempWorkspace);

        $this->assertSame('nodejs', $paths->getActiveTemplate());
        $cacheFile = $paths->getCacheDir('wiring.json');
        $this->assertFileExists($cacheFile);

        $expectedRelPath = $paths->getRelativePath('nodejs/cache/wiring.json');
        $this->assertSame($expectedRelPath, $paths->getRelativeCachePath('wiring.json'));
    }

    public function test_it_extracts_neighborhood_context_for_polyglot_task(): void
    {
        mkdir($this->tempWorkspace . '/src', 0777, true);

        File::put(
            $this->tempWorkspace . '/src/PaymentProcessor.php',
            "<?php\nnamespace App\Billing;\nuse Stripe\Stripe;\nclass PaymentProcessor {}\n"
        );

        $this->service->scan($this->tempWorkspace);

        $context = $this->service->getNeighborhoodContext('Refactor PaymentProcessor billing workflow');

        $text = is_array($context) ? ($context['summary'] ?? '') : $context;
        $this->assertStringContainsString('PaymentProcessor', $text);
        $this->assertStringContainsString('src/PaymentProcessor.php', $text);
    }
}
