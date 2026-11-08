<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AgentPathService;
use App\Services\TemplateManager;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InitCommandTest extends TestCase
{
    private string $originalCwd;
    private string $tempWorkspace;
    private AgentPathService $paths;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string) getcwd();
        $this->tempWorkspace = sys_get_temp_dir() . '/pncagent_init_test_' . uniqid();
        mkdir($this->tempWorkspace, 0777, true);
        chdir($this->tempWorkspace);

        $this->paths = new AgentPathService($this->tempWorkspace);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);

        if (File::isDirectory($this->tempWorkspace)) {
            File::deleteDirectory($this->tempWorkspace);
        }

        parent::tearDown();
    }

    public function test_it_initializes_with_flags_non_interactively(): void
    {
        $this->artisan('init', [
            '--name' => 'FlagWorkspace',
            '--template' => 'laravel',
            '--ai-endpoint' => 'https://api.openai.com/v1',
            '--ai-model' => 'gpt-4o',
            '--ai-key' => 'sk-test-ai-key',
            '--search-endpoint' => 'https://api.tavily.com/search',
            '--search-key' => 'tvly-test-search-key',
        ])->assertSuccessful();

        $this->assertFileExists($this->paths->getConfigPath());
        $this->assertFileExists($this->paths->getEnvPath());
        $this->assertFileExists($this->paths->getConfigPath('laravel'));

        $config = json_decode((string) File::get($this->paths->getConfigPath()), true);
        $this->assertSame('FlagWorkspace', $config['project_name']);
        $this->assertArrayHasKey('laravel', $config['stack']['frameworks']);
        $this->assertArrayHasKey('context_scanning', $config);

        $envContent = (string) File::get($this->paths->getEnvPath());
        $this->assertStringContainsString('AGENT_TEMPLATE="laravel"', $envContent);
        $this->assertStringContainsString('AI_KEY="sk-test-ai-key"', $envContent);
    }

    public function test_it_initializes_with_default_ollama_and_search_settings(): void
    {
        $this->artisan('init', [
            '--name' => 'DefaultWorkspace',
            '--template' => 'php',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $config = json_decode((string) File::get($this->paths->getConfigPath()), true);
        $this->assertSame('http://localhost:11434/v1', $config['ai_settings']['endpoint']);
        $this->assertSame('qwen2.5-coder:7b', $config['ai_settings']['model']);

        $envContent = (string) File::get($this->paths->getEnvPath());
        $this->assertStringContainsString('AI_KEY="ollama"', $envContent);
        $this->assertStringContainsString('AI_ENDPOINT="http://localhost:11434/v1"', $envContent);
        $this->assertStringContainsString('AI_MODEL="qwen2.5-coder:7b"', $envContent);
        $this->assertStringContainsString('SEARCH_ENDPOINT="https://api.tavily.com/search"', $envContent);
    }

    public function test_it_initializes_multiple_templates_and_tracks_active_in_env(): void
    {
        $this->artisan('init', [
            '--name' => 'MultiApp',
            '--template' => 'php,laravel',
            '--active-template' => 'php',
            '--ai-endpoint' => 'https://api.openai.com/v1',
            '--ai-model' => 'gpt-4o',
            '--ai-key' => 'sk-test-ai-key',
            '--search-endpoint' => 'https://api.tavily.com/search',
            '--search-key' => 'tvly-test-search-key',
        ])->assertSuccessful();

        $this->assertFileExists($this->paths->getConfigPath('php'));
        $this->assertFileExists($this->paths->getConfigPath('laravel'));
        $this->assertFileExists($this->paths->getConfigPath());

        $envContent = (string) File::get($this->paths->getEnvPath());
        $this->assertStringContainsString('AGENT_TEMPLATE="php"', $envContent);

        $activeConfig = json_decode((string) File::get($this->paths->getConfigPath()), true);
        $this->assertSame('php', $activeConfig['stack']['primary_language']);
    }

    public function test_it_recognizes_template_as_first_argument(): void
    {
        $this->artisan('init', [
            'name' => 'php',
            '--name' => 'MyPhpApp',
            '--template' => 'php',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertFileExists($this->paths->getConfigPath('php'));
        $config = json_decode((string) File::get($this->paths->getConfigPath()), true);
        $this->assertSame('MyPhpApp', $config['project_name']);
        $this->assertSame('php', $config['stack']['primary_language']);
    }

    public function test_it_prompts_interactively_when_options_are_missing(): void
    {
        $templateManager = app(TemplateManager::class);
        $availableTemplates = $templateManager->getAvailableTemplates();
        $relEnv = $this->paths->getRelativeEnvPath();

        $this->artisan('init')
            ->expectsChoice(
                'Select workspace project template',
                'php',
                $availableTemplates
            )
            ->expectsQuestion('Additional templates to include (comma-separated, leave empty for none)', '')
            ->expectsQuestion('What is the project name?', 'InteractiveWorkspace')
            ->expectsQuestion('AI Endpoint URL', 'http://localhost:11434/v1')
            ->expectsQuestion('AI Model identifier', 'codellama:latest')
            ->expectsQuestion('AI API Key', 'ollama')
            ->expectsQuestion('Web Search Endpoint URL', 'https://api.tavily.com/search')
            ->expectsQuestion("Web Search API Key (leave empty to configure later in {$relEnv})", '')
            ->assertSuccessful();

        $this->assertFileExists($this->paths->getConfigPath('php'));
        $this->assertFileExists($this->paths->getConfigPath());

        $envContent = (string) File::get($this->paths->getEnvPath());
        $this->assertStringContainsString('AGENT_TEMPLATE="php"', $envContent);
        $this->assertStringContainsString('AI_KEY="ollama"', $envContent);
        $this->assertStringContainsString('AI_ENDPOINT="http://localhost:11434/v1"', $envContent);
        $this->assertStringContainsString('AI_MODEL="codellama:latest"', $envContent);
    }
}
