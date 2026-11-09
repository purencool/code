<?php

declare(strict_types=1);

namespace App\TestSandbox;

use InvalidArgumentException;

class SandboxService
{
    /**
     * Constructor Dependency Injection: Injects an implementation of SandboxLoggerInterface.
     */
    public function __construct(
        private readonly SandboxLoggerInterface $logger
    ) {
    }

    /**
     * Process an action and return structured metadata.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function process(string $action, array $payload = []): array
    {
        $this->logger->log("Executing action: {$action}");

        return [
            'status' => 'success',
            'action' => $action,
            'timestamp' => time(),
            'payload' => $payload,
        ];
    }

    /**
     * Validate an input string against length requirements.
     */
    public function validate(string $input, int $minLength = 1): bool
    {
        $isValid = strlen(trim($input)) >= $minLength;
        $status = $isValid ? 'passed' : 'failed';
        $this->logger->log("Validation {$status} for input: '{$input}'");

        return $isValid;
    }

    /**
     * Perform basic mathematical operations with validation.
     */
    public function calculate(int $a, int $b, string $operation = 'add'): int
    {
        $result = match (strtolower($operation)) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            default => throw new InvalidArgumentException("Unsupported operation: {$operation}"),
        };

        $this->logger->log("Calculated {$a} {$operation} {$b} = {$result}");

        return $result;
    }

    /**
     * Retrieve all recorded log entries.
     *
     * @return array<int, string>
     */
    public function getExecutionLogs(): array
    {
        return $this->logger->getLogs();
    }

    /**
     * Clear recorded state.
     */
    public function reset(): void
    {
        $this->logger->clear();
    }
}
