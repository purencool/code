<?php

declare(strict_types=1);

namespace App\Services\Agent;

use Illuminate\Support\Facades\File;
use OutOfBoundsException;
use RuntimeException;

class DiffPatcher
{
    /**
     * Read a specific 1-indexed line range from a file.
     *
     * @return array<int, string> Keyed by 1-based line number.
     * @throws OutOfBoundsException
     */
    public function readLines(string $filePath, int $startLine, int $endLine): array
    {
        if (!File::exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $content = File::get($filePath);
        $lines = explode("\n", $content);
        $totalLines = count($lines);

        if ($startLine < 1 || $endLine > $totalLines || $startLine > $endLine) {
            throw new OutOfBoundsException(
                "Requested line range ({$startLine}-{$endLine}) exceeds file line count ({$totalLines})."
            );
        }

        $result = [];
        for ($i = $startLine; $i <= $endLine; $i++) {
            $result[$i] = $lines[$i - 1];
        }

        return $result;
    }

    /**
     * Apply targeted search-and-replace block, ensuring unique anchor match.
     *
     * @throws RuntimeException
     */
    public function applySearchReplace(string $filePath, string $searchBlock, string $replaceBlock): void
    {
        if (!File::exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $content = File::get($filePath);
        $matchesCount = substr_count($content, $searchBlock);

        if ($matchesCount === 0) {
            throw new RuntimeException('Search block not found in file: target anchor does not match.');
        }

        if ($matchesCount > 1) {
            throw new RuntimeException(
                "Search block matched {$matchesCount} times. Target anchor must match uniquely."
            );
        }

        $updated = str_replace($searchBlock, $replaceBlock, $content);
        File::put($filePath, $updated);
    }

    /**
     * Apply a unified diff hunk to the specified target file.
     *
     * @throws RuntimeException
     */
    public function applyUnifiedDiff(string $filePath, string $unifiedDiff): void
    {
        if (!File::exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $content = File::get($filePath);
        $fileLines = explode("\n", $content);

        $diffLines = explode("\n", $unifiedDiff);
        $hunks = [];
        $currentHunk = null;

        foreach ($diffLines as $line) {
            if (preg_match('/^@@\s+-(\d+)(?:,(\d+))?\s+\+(\d+)(?:,(\d+))?\s+@@/', $line, $matches)) {
                if ($currentHunk !== null) {
                    $hunks[] = $currentHunk;
                }
                $currentHunk = [
                    'origStart' => (int) $matches[1],
                    'origCount' => isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 1,
                    'lines'     => [],
                ];
            } elseif ($currentHunk !== null) {
                $currentHunk['lines'][] = $line;
            }
        }

        if ($currentHunk !== null) {
            $hunks[] = $currentHunk;
        }

        if (empty($hunks)) {
            throw new RuntimeException('No valid diff hunks detected in input.');
        }

        // Apply hunks in reverse line order so subsequent line positions remain stable
        usort($hunks, fn (array $a, array $b) => $b['origStart'] <=> $a['origStart']);

        foreach ($hunks as $hunk) {
            $startIdx = $hunk['origStart'] - 1;
            $count = $hunk['origCount'];

            $replacementLines = [];
            foreach ($hunk['lines'] as $hLine) {
                if ($hLine === '') {
                    continue;
                }

                $prefix = $hLine[0];
                $text = substr($hLine, 1);

                if ($prefix === ' ' || $prefix === '+') {
                    $replacementLines[] = $text;
                }
                // Lines with prefix '-' are discarded
            }

            array_splice($fileLines, $startIdx, $count, $replacementLines);
        }

        File::put($filePath, implode("\n", $fileLines));
    }
}
