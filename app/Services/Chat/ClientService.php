<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Agents\ChatAgent;
use App\Services\AgentContextService;
use App\Services\Agent\ContextBudgeter;

class ClientService
{
    public function __construct(
        private readonly ChatAgent $chatAgent,
        private readonly ClientLogger $logger,
        private readonly ContextBudgeter $budgeter,
        private readonly AgentContextService $contextService
    ) {
    }

    public function getLogFilePath(): string
    {
        return $this->logger->getLogFilePath();
    }

    /**
     * @return array{is_command: bool, should_exit: bool, response: string}
     */
    public function processInput(string $input, bool $log = true): array
    {
        $trimmed = trim($input);

        if (str_starts_with($trimmed, '/')) {
            return $this->handleSlashCommand($trimmed);
        }

        if ($trimmed === 'exit' || $trimmed === 'quit') {
            return [
                'is_command' => true,
                'should_exit' => true,
                'response' => 'Session terminated.',
            ];
        }

        if ($log) {
            $this->logger->logTurn('USER', $trimmed);
        }

        $response = $this->chatAgent->send($trimmed);

        if ($log) {
            $this->logger->logTurn('ASSISTANT', $response);
        }

        return [
            'is_command' => false,
            'should_exit' => false,
            'response' => $response,
        ];
    }

    /**
     * @return array{is_command: bool, should_exit: bool, response: string}
     */
    private function handleSlashCommand(string $command): array
    {
        $cmd = strtolower(trim($command));

        if ($cmd === '/exit' || $cmd === '/quit') {
            return [
                'is_command' => true,
                'should_exit' => true,
                'response' => 'Session terminated.',
            ];
        }

        if ($cmd === '/clear') {
            $this->logger->clear();

            return [
                'is_command' => true,
                'should_exit' => false,
                'response' => 'Session history cleared.',
            ];
        }

        if ($cmd === '/history') {
            $history = $this->logger->readHistory();

            return [
                'is_command' => true,
                'should_exit' => false,
                'response' => $history !== '' ? $history : 'No history found.',
            ];
        }

        if ($cmd === '/help') {
            $helpText = "Available commands:\n"
                . "  /help    - Show available commands\n"
                . "  /clear   - Clear the current session log\n"
                . "  /history - Display current session history\n"
                . "  /exit    - Exit the chat client";

            return [
                'is_command' => true,
                'should_exit' => false,
                'response' => $helpText,
            ];
        }

        return [
            'is_command' => true,
            'should_exit' => false,
            'response' => "Unknown command: {$command}. Type /help for assistance.",
        ];
    }
}
