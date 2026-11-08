<?php

declare(strict_types=1);

namespace App\Services\Agent;

class ContextBudgeter
{
    /**
     * Sanitize PHPUnit/Pest trace by removing vendor frames and limiting application frames.
     */
    public function sanitizeTestTrace(string $trace, int $maxAppFrames = 3): string
    {
        $lines = explode("\n", $trace);
        $filteredLines = [];
        $appFrameCount = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $isFrame = (bool) preg_match('/^#\d+\s+/', $trimmed);

            if ($isFrame) {
                // Strip out vendor frames
                if (str_contains($line, '/vendor/') || str_contains($line, '\\vendor\\')) {
                    continue;
                }

                // Bound application frames to context budget
                if ($appFrameCount >= $maxAppFrames) {
                    continue;
                }

                $appFrameCount++;
                $filteredLines[] = $line;
                continue;
            }

            // Exclude standalone lines referencing vendor stack paths
            if (str_contains($line, '/vendor/') || str_contains($line, '\\vendor\\')) {
                continue;
            }

            $filteredLines[] = $line;
        }

        return implode("\n", $filteredLines);
    }

    /**
     * Truncate large log payloads preserving head and tail windows with an omission notice.
     */
    public function truncateLogWindow(string $payload, int $headCount = 5, int $tailCount = 5): string
    {
        $lines = explode("\n", $payload);
        $totalLines = count($lines);

        if ($totalLines <= ($headCount + $tailCount)) {
            return $payload;
        }

        $head = array_slice($lines, 0, $headCount);
        $tail = array_slice($lines, -$tailCount);
        $omitted = $totalLines - $headCount - $tailCount;

        $middle = ["[... {$omitted} lines omitted for context budget ...]"];

        return implode("\n", array_merge($head, $middle, $tail));
    }

    /**
     * Enforce a hard character/token budget ceiling on prompt payloads.
     */
    public function enforceCeiling(string $text, int $characterCeiling): string
    {
        if (strlen($text) <= $characterCeiling) {
            return $text;
        }

        return substr($text, 0, $characterCeiling) . "\n... [TRUNCATED DUE TO CONTEXT BUDGET]";
    }
}
