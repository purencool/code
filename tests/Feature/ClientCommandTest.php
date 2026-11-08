<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AgentContextService;
use App\Services\Chat\ClientService;
use Tests\TestCase;

/**
 * @covers \App\Commands\ClientCommand
 */
class ClientCommandTest extends TestCase
{
    public function test_displays_startup_banner_and_exits_cleanly(): void
    {
        $mockContext = $this->createMock(AgentContextService::class);
        $mockContext->method('getWorkspaceRoot')->willReturn('/workspace/project');
        $this->app->instance(AgentContextService::class, $mockContext);

        $mockClient = $this->createMock(ClientService::class);
        $mockClient->method('getLogFilePath')->willReturn('/workspace/project/.agent/environment/chatdate-client.log');
        $mockClient->expects($this->once())
            ->method('processInput')
            ->with('/exit', true)
            ->willReturn([
                'is_command' => true,
                'should_exit' => true,
                'response' => 'Session terminated.',
            ]);
        $this->app->instance(ClientService::class, $mockClient);

        $this->artisan('client')
            ->expectsQuestion('>', '/exit')
            ->assertExitCode(0);
    }

    public function test_executes_with_no_log_flag(): void
    {
        $mockContext = $this->createMock(AgentContextService::class);
        $mockContext->method('getWorkspaceRoot')->willReturn('/workspace/project');
        $this->app->instance(AgentContextService::class, $mockContext);

        $mockClient = $this->createMock(ClientService::class);
        $mockClient->method('getLogFilePath')->willReturn('/workspace/project/.agent/environment/chatdate-client.log');
        $mockClient->expects($this->once())
            ->method('processInput')
            ->with('/exit', false)
            ->willReturn([
                'is_command' => true,
                'should_exit' => true,
                'response' => 'Goodbye.',
            ]);
        $this->app->instance(ClientService::class, $mockClient);

        $this->artisan('client', ['--no-log' => true])
            ->expectsQuestion('>', '/exit')
            ->assertExitCode(0);
    }
}
