<?php

declare(strict_types=1);

namespace App\TestSandbox;

interface SandboxLoggerInterface
{
    public function log(string $message): void;

    /**
     * @return array<int, string>
     */
    public function getLogs(): array;

    public function clear(): void;
}
