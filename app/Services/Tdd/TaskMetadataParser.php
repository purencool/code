<?php

declare(strict_types=1);

namespace App\Services\Tdd;

use RuntimeException;

class TaskMetadataParser
{
    /**
     * Parse task metadata strictly from markdown headers.
     *
     * @return array{target_type: string, target_test: string, target_class: string, dependencies: array<int, string>, task: string}
     *
     * @throws RuntimeException If neither Target Class nor Target Test is defined in the markdown.
     */
    public function parse(string $rawTask): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $rawTask);
        $lines = explode("\n", $normalized);

        $targetType = 'TDD';
        $targetTest = null;
        $targetClass = null;
        $dependencies = [];
        $taskBodyLines = [];
        $inTaskBody = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                if ($inTaskBody) {
                    $taskBodyLines[] = '';
                }
                continue;
            }

            $clean = ltrim($trimmed, "-* \t");
            $clean = str_ireplace(['**', '__', '`'], '', $clean);

            if (str_contains($clean, ':')) {
                [$keyPart, $valPart] = explode(':', $clean, 2);
                $key = strtolower(trim($keyPart));
                $val = trim($valPart);

                if ($key === 'target type' || $key === 'type') {
                    $upper = strtoupper($val);
                    if ($upper !== '') {
                        $targetType = $upper;
                    }
                    $inTaskBody = false;
                    continue;
                }

                if ($key === 'target test') {
                    $targetTest = $val;
                    $inTaskBody = false;
                    continue;
                }

                if ($key === 'target class') {
                    $targetClass = $val;
                    $inTaskBody = false;
                    continue;
                }

                if ($key === 'dependencies') {
                    $rawDeps = explode(',', $val);
                    $dependencies = array_values(array_filter(array_map('trim', $rawDeps)));
                    $inTaskBody = false;
                    continue;
                }

                if ($key === 'task') {
                    $inTaskBody = true;
                    if ($val !== '') {
                        $taskBodyLines[] = $val;
                    }
                    continue;
                }
            }

            if ($inTaskBody) {
                $taskBodyLines[] = $line;
            } else {
                $taskBodyLines[] = $trimmed;
            }
        }

        if ($targetTest === null && $targetClass !== null) {
            $cleanClass = str_starts_with($targetClass, 'app/') ? substr($targetClass, 4) : $targetClass;
            $testDir = str_starts_with($cleanClass, 'Commands/') ? 'tests/Feature/' : 'tests/Unit/';
            $targetTest = $testDir . str_replace('.php', 'Test.php', $cleanClass);
        } elseif ($targetClass === null && $targetTest !== null) {
            $cleanTest = $targetTest;
            if (str_starts_with($cleanTest, 'tests/Unit/')) {
                $cleanTest = substr($cleanTest, 11);
            } elseif (str_starts_with($cleanTest, 'tests/Feature/')) {
                $cleanTest = substr($cleanTest, 14);
            } elseif (str_starts_with($cleanTest, 'tests/')) {
                $cleanTest = substr($cleanTest, 6);
            }
            $targetClass = 'app/' . str_replace('Test.php', '.php', $cleanTest);
        }

        if ($targetTest === null || $targetClass === null) {
            throw new RuntimeException("Task markdown must specify 'Target Class' or 'Target Test'.");
        }

        return [
            'target_type'   => $targetType,
            'target_test'   => $targetTest,
            'target_class'  => $targetClass,
            'dependencies'  => $dependencies,
            'task'          => trim(implode("\n", $taskBodyLines)),
        ];
    }
}
