<?php

declare(strict_types=1);

namespace Tests\Unit\Agents;

use App\Agents\ChatAgent;
use App\Services\AgentContextService;
use NeuronAI\Agent\Agent;
use NeuronAI\Providers\AIProviderInterface;
use Tests\TestCase;

/**
 * @covers \App\Agents\ChatAgent
 */
class ChatAgentTest extends TestCase
{
    public function test_extends_neuron_ai_agent(): void
    {
        $contextService = $this->createMock(AgentContextService::class);
        $contextService->method('getWorkspaceRoot')->willReturn('/workspace');

        $provider = $this->createMock(AIProviderInterface::class);

        // Inject provider directly to avoid mocking static AIProviderFactory methods
        $agent = new ChatAgent($contextService, $provider);

        $this->assertInstanceOf(Agent::class, $agent);
    }

    public function test_initializes_with_workspace_system_instructions(): void
    {
        $contextService = $this->createMock(AgentContextService::class);
        $contextService->method('getWorkspaceRoot')->willReturn('/test/workspace');

        $provider = $this->createMock(AIProviderInterface::class);

        $agent = new ChatAgent($contextService, $provider);
        $instructions = $agent->getSystemInstructions();

        $this->assertStringContainsString('/test/workspace', $instructions);
    }
}
