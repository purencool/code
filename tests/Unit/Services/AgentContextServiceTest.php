<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AgentContextService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AgentContextServiceTest extends TestCase
{
    private string $tempWorkspace;
    private AgentContextService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempWorkspace = sys_get_temp_dir() . '/pncagent_context_test_' . uniqid();
        mkdir($this->tempWorkspace . '/.agent', 0777, true);
        $this->service = new AgentContextService($this->tempWorkspace);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempWorkspace);
        parent::tearDown();
    }

    public function test_it_loads_empty_config_when_missing(): void
    {
        $config = $this->service->load();
        $this->assertSame([], $config);
    }

    public function test_it_syncs_and_reads_env_variables(): void
    {
        $this->service->syncEnvVariables([
            'AI_KEY' => 'sk-test-key-123',
            'AI_MODEL' => 'gpt-4o',
            'EMPTY_KEY' => '',
        ]);

        $envVars = $this->service->getEnvVariables();

        $this->assertSame('sk-test-key-123', $envVars['AI_KEY']);
        $this->assertSame('gpt-4o', $envVars['AI_MODEL']);
        $this->assertSame('', $envVars['EMPTY_KEY']);
    }
}
