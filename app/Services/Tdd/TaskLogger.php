<?php

declare(strict_types=1);

namespace App\Services\Tdd;

use App\Services\AgentContextService;
use Illuminate\Support\Facades\File;

class TaskLogger
{
    public function __construct(protected AgentContextService $context)
    {
    }

    public function getLogPath(): string
    {
        return $this->context->paths()->getTemplateDir() . '/console_history.md';
    }

    public function getRelativeLogPath(): string
    {
        $workspace =$this->context->getWorkspaceRoot();
        $logPath =$this->getLogPath();

        return ltrim(str_replace($workspace, '',$logPath), '/\\');
    }

    public function logRequest(string $rawInput): void
    {
        $logPath =$this->getLogPath();
        $dir = dirname($logPath);

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');$divider = str_repeat('-', 60);

        $entry = "\n" . $divider . "\n# Session: " . $timestamp . "\n" . $divider
            . "\n\n### Prompt Input:\n```text\n" . $rawInput . "\n```\n\n";

        File::append($logPath,$entry);
    }

    /**
     * @param array<int, string> $testFiles
     * @param array<int, string> $implementationFiles
     */
    public function logTaskOutcome(
        int $stepNum,
        int $totalSteps,
        string $taskDescription,
        array $testFiles,
        array $implementationFiles,
        string $status
    ): void {
        $logPath =$this->getLogPath();

        $testList = empty($testFiles)
            ? '  None'
            : implode("\n", array_map(fn (string $f): string => "  - `" . $f . "`", $testFiles));

        $implList = empty($implementationFiles)
            ? '  None'
            : implode("\n", array_map(fn (string $f): string => "  - `" . $f . "`", $implementationFiles));

        $timestamp = date('H:i:s');

        $entry = "#### Task [" . $stepNum . "/" . $totalSteps . "] — [" . $status . "] at " . $timestamp . "\n"
            . "**Description:**\n> " . $taskDescription . "\n\n"
            . "**Generated Tests:**\n" . $testList . "\n\n"
            . "**Generated Implementations:**\n" . $implList . "\n\n";

        File::append($logPath,$entry);
    }
}
