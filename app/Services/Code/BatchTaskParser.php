<?php

declare(strict_types=1);

namespace App\Services\Code;

class BatchTaskParser
{
    /**
     * Splits input line-by-line into discrete task string blocks using '---' as delimiter.
     *
     * @return array<int, string>
     */
    public function parse(string $rawInput): array
    {
        // 1. Normalize line endings and create array of lines
        $normalized = str_replace(["\r\n", "\r"], "\n", $rawInput);
        $lines = explode("\n", $normalized);

        $taskBlocks = [];
        $currentTaskLines = [];

        // 2. Scan array elements for boundary delimiter (--- or ***)
        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($this->isBoundaryDelimiter($trimmed)) {
                if (!empty($currentTaskLines)) {
                    $taskBlocks[] = trim(implode("\n", $currentTaskLines));
                    $currentTaskLines = [];
                }
                continue;
            }

            $currentTaskLines[] = $line;
        }

        // Add final task array
        if (!empty($currentTaskLines)) {
            $taskBlocks[] = trim(implode("\n", $currentTaskLines));
        }

        // 3. Filter out empty chunks
        $filtered = array_values(array_filter($taskBlocks, fn(string $block): bool => $block !== ''));

        return !empty($filtered) ? $filtered : [trim($rawInput)];
    }

    protected function isBoundaryDelimiter(string $trimmed): bool
    {
        if (str_starts_with($trimmed, '---')) {
            return trim($trimmed, '-') === '';
        }

        if (str_starts_with($trimmed, '***')) {
            return trim($trimmed, '*') === '';
        }

        return false;
    }
}
