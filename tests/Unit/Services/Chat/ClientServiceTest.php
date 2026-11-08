<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Chat;

use App\Agents\ChatAgent;
use App\Services\AgentContextService;
use App\Services\Agent\ContextBudgeter;
use App\Services\Chat\ClientLogger;
use App\Services\Chat\ClientService;
use Tests\TestCase;

/**
 * @covers \App\Services\Chat\ClientService
 */
class ClientServiceTest extends TestCase
{
    private ChatAgent $chatAgent;
    private ClientLogger $logger;
    private ContextBudgeter $budgeter;
    private AgentContextService $contextService;
    private ClientService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chatAgent = $this->createMock(ChatAgent::class);
        $this->logger = $this->createMock(ClientLogger::class);
        $this->budgeter = $this->createMock(ContextBudgeter::class);
        $this->contextService = $this->createMock(AgentContextService::class);

        $this->service = new ClientService(
            $this->chatAgent,
            $this->logger,
            $this->budgeter,
            $this->contextService
        );
    }

    public function test_intercepts_clear_slash_command_without_invoking_llm(): void
    {
        $this->logger->expects($this->once())->method('clear');
        $this->chatAgent->expects($this->never())->method('send');

        $result = $this->service->processInput('/clear');

        $this->assertTrue($result['is_command']);
        $this->assertStringContainsString('cleared', strtolower($result['response']));
    }

    public function test_routes_user_message_to_agent_and_persists_turn(): void
    {
        $this->chatAgent->expects($this->once())
            ->method('send')
            ->with('Can you refactor this?')
            ->willReturn('Yes, refactoring completed.');

        $this->logger->expects($this->exactly(2))->method('logTurn');

        $result = $this->service->processInput('Can you refactor this?');

        $this->assertFalse($result['is_command']);
        $this->assertSame('Yes, refactoring completed.', $result['response']);
    }
}
