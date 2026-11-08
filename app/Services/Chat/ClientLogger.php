<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Services\AgentContextService;
use App\Services\AgentPathService;
use Illuminate\Support\Facades\File;
use Throwable;

class ClientLogger
{
    private string $logFilePath;

    public function __construct(
        private readonly AgentContextService $contextService,
        private readonly AgentPathService $pathService
    ) {
        $envDir = $this->pathService->getAgentDir() . '/environment';
        File::ensureDirectoryExists($envDir);
        $this->logFilePath = $envDir . '/chatdate-client.log';
    }

    public function getLogFilePath(): string
    {
        return $this->logFilePath;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logTurn(string $role, string $message, array $metadata = []): void
    {
        try {
            $timestamp = date('c');
            $entry = "[{$timestamp}] [{$role}]: " . trim($message) . "\n";
            File::append($this->logFilePath, $entry);
        } catch (Throwable) {
            // Silently handle filesystem write errors to protect chat continuity
        }
    }

    public function readHistory(): string
    {
        if (!File::exists($this->logFilePath)) {
            return '';
        }

        return (string) File::get($this->logFilePath);
    }

    public function clear(): void
    {
        try {
            File::put($this->logFilePath, '');
        } catch (Throwable) {
            // Silently handle clear errors
        }
    }
}
