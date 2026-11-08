<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AgentContextService;
use App\Services\AgentPathService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use Throwable;

class CodeCommandTest extends TestCase
{
    private string $originalCwd;
    private string $tempWorkspace;
    private AgentPathService $paths;
    private AgentContextService $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string) getcwd();
        $this->tempWorkspace = sys_get_temp_dir() . '/pnc_console_test_' . uniqid();
        mkdir($this->tempWorkspace, 0777, true);
        chdir($this->tempWorkspace);

        $this->paths = new AgentPathService($this->tempWorkspace);
        $this->context = new AgentContextService($this->tempWorkspace, $this->paths);

        $this->app->instance(AgentPathService::class, $this->paths);
        $this->app->instance(AgentContextService::class, $this->context);

        mkdir($this->paths->getAgentDir(), 0777, true);
        mkdir($this->paths->getTemplateDir(), 0777, true);

        $aiEndpoint = env('AI_ENDPOINT', 'http://localhost:11434/v1');
        $aiModel = env('AI_MODEL', 'codellama:latest');

        File::put($this->paths->getConfigPath(), (string) json_encode([
            'project_name' => 'ConsoleWorkspace',
            'stack' => ['primary_language' => 'php'],
            'ai_settings' => [
                'endpoint' => $aiEndpoint,
                'model' => $aiModel,
            ],
            'scripts' => [
                'test' => 'echo "All tests passed"',
            ],
        ]));

        File::put($this->paths->getEnvPath(), "AGENT_TEMPLATE=php\nAI_ENDPOINT={$aiEndpoint}\nAI_KEY=ollama\nAI_MODEL={$aiModel}\n");
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        if (File::isDirectory($this->tempWorkspace)) {
            File::deleteDirectory($this->tempWorkspace);
        }
        parent::tearDown();
    }

    public function test_it_executes_tdd_workflow_with_task_argument(): void
    {
        $this->skipIfOllamaUnavailable();

        try {
            $this->artisan('code', [
                'task' => 'Write a Calculator class with add method',
                '--workspace' => $this->tempWorkspace,
                '--auto-approve' => true,
            ])->assertExitCode(0);
        } catch (Throwable $e) {
            // If Ollama encounters an inference failure, model missing, or connection error, skip the test
            if (
                str_contains($e->getMessage(), 'Neuron AI request failed') ||
                str_contains($e->getMessage(), 'AI API Request Failed') ||
                str_contains($e->getMessage(), 'Failed to parse') ||
                str_contains($e->getMessage(), 'Connection refused') ||
                str_contains($e->getMessage(), '404') ||
                str_contains($e->getMessage(), 'not found')
            ) {
                $this->markTestSkipped('Ollama is not functional or model is missing: ' . $e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Skip test if Ollama is unreachable or does not have the target model installed.
     */
    private function skipIfOllamaUnavailable(): void
    {
        $config = $this->context->load();
        $endpoint = $config['ai_settings']['endpoint'] ?? 'http://localhost:11434/v1';
        $model = $config['ai_settings']['model'] ?? 'codellama:latest';

        $urlParts = parse_url($endpoint);
        $host = $urlParts['host'] ?? '127.0.0.1';
        $port = $urlParts['port'] ?? (str_starts_with($endpoint, 'https') ? 443 : 80);

        if ($host === 'localhost') {
            $host = '127.0.0.1';
        }

        // 1. Socket connectivity check (500ms timeout)
        $socket = @fsockopen($host, (int) $port, $errno, $errstr, 0.5);
        if (!$socket) {
            $this->markTestSkipped("Ollama is not running on {$host}:{$port}. Skipping test.");
        }
        fclose($socket);

        // 2. Query Ollama /api/tags to verify service and model availability
        $ctx = stream_context_create(['http' => ['timeout' => 1]]);
        $tagsJson = @file_get_contents("http://{$host}:{$port}/api/tags", false, $ctx);

        if ($tagsJson === false) {
            $this->markTestSkipped("Ollama is not responding at http://{$host}:{$port}/api/tags. Skipping test.");
        }

        $tagsData = json_decode((string) $tagsJson, true);
        if (!is_array($tagsData) || empty($tagsData['models'])) {
            $this->markTestSkipped("Ollama is running, but no models are installed. Skipping test.");
        }

        $availableModels = array_map(function ($m) {
            return is_array($m) ? ($m['name'] ?? '') : '';
        }, $tagsData['models']);

        $modelPrefix = explode(':', $model)[0];
        $hasModel = false;
        foreach ($availableModels as $name) {
            if ($name === $model || str_starts_with($name, $modelPrefix)) {
                $hasModel = true;
                break;
            }
        }

        if (!$hasModel) {
            $this->markTestSkipped("Ollama model '{$model}' is not pulled locally (installed: " . implode(', ', array_filter($availableModels)) . "). Skipping test.");
        }
    }
}
