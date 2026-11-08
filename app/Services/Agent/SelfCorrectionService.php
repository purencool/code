<?php

declare(strict_types=1);

namespace App\Services\Agent;

use Symfony\Component\Process\Process;

class SelfCorrectionService
{
    /**
     * Run php -l syntax check on arbitrary code string without leaving artifacts on disk.
     *
     * @return array{valid: bool, error: ?string, line: ?int}
     */
    public function lint(string $phpCode): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'pnc_lint_') . '.php';
        file_put_contents($tempFile, $phpCode);

        $process = new Process(['php', '-l', $tempFile]);
        $process->run();

        $output = $process->getOutput() . $process->getErrorOutput();
        @unlink($tempFile);

        if ($process->isSuccessful()) {
            return [
                'valid' => true,
                'error' => null,
                'line'  => null,
            ];
        }

        $line = null;
        if (preg_match('/on line (\d+)/i', $output, $matches)) {
            $line = (int) $matches[1];
        }

        return [
            'valid' => false,
            'error' => trim($output),
            'line'  => $line,
        ];
    }

    /**
     * Parse raw PHPUnit / Pest output into structured feedback for prompt injection.
     *
     * @return ?array{assertion: string, file: string, line: int, raw: string}
     */
    public function extractTestFailure(string $testOutput): ?array
    {
        if (
            !str_contains($testOutput, 'FAIL') &&
            !str_contains($testOutput, 'There was 1 failure') &&
            !str_contains($testOutput, 'Failed asserting that')
        ) {
            return null;
        }

        $assertion = null;
        $file = null;
        $line = null;

        if (preg_match('/Failed asserting that (.*?)(?:\n\n|\r\n\r\n|\n[A-Z]|\r\n[A-Z]|$)/s', $testOutput, $matches)) {
            $assertion = trim($matches[1]);
        }

        // Robust path matching for file and line number
        if (preg_match('/([^\s:]+\.php):(\d+)/', $testOutput, $matches)) {
            $file = $matches[1];
            $line = (int) $matches[2];
        }

        return [
            'assertion' => $assertion ?? 'Test assertion failed.',
            'file'      => $file ?? 'unknown',
            'line'      => $line ?? 0,
            'raw'       => trim($testOutput),
        ];
    }

    /**
     * Run bounded self-healing loop: lint -> execute tests -> feedback -> retry.
     *
     * @param callable(string|null $feedback, int $attempt): string $generatorFn
     * @param callable(string $code): array{passed: bool, output: string} $verifierFn
     * @param int $maxRetries
     * @return array{
     *     success: bool,
     *     attempts: int,
     *     final_code: ?string,
     *     error: ?string,
     *     history: array<int, array{attempt: int, type: string, message: string}>
     * }
     */
    public function runLoop(callable $generatorFn, callable $verifierFn, int $maxRetries = 3): array
    {
        $feedback = null;
        $history = [];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $code = $generatorFn($feedback, $attempt);

            // 1. Internal Lint/Syntax Check Gate
            $lint = $this->lint($code);
            if (!$lint['valid']) {
                $errorMsg = "Syntax Error on line {$lint['line']}: {$lint['error']}";
                $history[] = [
                    'attempt' => $attempt,
                    'type'    => 'syntax_error',
                    'message' => $errorMsg,
                ];
                $feedback = "{$errorMsg}. Fix all syntax errors before returning code.";
                continue;
            }

            // 2. Execution & Verification Gate
            $result = $verifierFn($code);
            if ($result['passed']) {
                return [
                    'success'    => true,
                    'attempts'   => $attempt,
                    'final_code' => $code,
                    'error'      => null,
                    'history'    => $history,
                ];
            }

            // 3. Test Failure Parsing & Feedback Generation
            $failure = $this->extractTestFailure($result['output']);
            $failureMsg = $failure !== null
                ? "Failed asserting that {$failure['assertion']} at {$failure['file']}:{$failure['line']}"
                : "Test Suite Failure: " . substr($result['output'], 0, 500);

            $history[] = [
                'attempt' => $attempt,
                'type'    => 'test_failure',
                'message' => $failureMsg,
            ];

            $feedback = "Your solution failed unit tests:\n{$failureMsg}\nRefactor your code to resolve this failure.";
        }

        return [
            'success'    => false,
            'attempts'   => $maxRetries,
            'final_code' => null,
            'error'      => $feedback ?? 'Exhausted retry budget without passing verification.',
            'history'    => $history,
        ];
    }
}
