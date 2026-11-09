<?php

declare(strict_types=1);

namespace App\TestSandbox;

class ArraySandboxLogger implements SandboxLoggerInterface
{
    /**
     * @var array<int, string>
     */
    private array $logs = [];

    public function log(string $message): void
    {
        $this->logs[] = $message;
    }

    /**
     * @return array<int, string>
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    public function count(): int
    {
        return count($this->logs);
    }

    public function clear(): void
    {
        $this->logs = [];
    }
}
