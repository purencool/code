<?php

declare(strict_types=1);

namespace App\Services\Agent;

use Illuminate\Support\Facades\File;

class ScratchpadMemory
{
    /**
     * @var array<string, array{description: string, status: string}>
     */
    protected array $tasks = [];

    /**
     * @var array<int, array{strategy: string, error: string}>
     */
    protected array $failures = [];

    /**
     * Set tasks dictionary where key is task ID and value is description.
     *
     * @param array<string, string> $tasks
     */
    public function setTasks(array $tasks): void
    {
        $this->tasks = [];
        foreach ($tasks as $key => $description) {
            $this->tasks[$key] = [
                'description' => $description,
                'status'      => 'pending',
            ];
        }
    }

    /**
     * Retrieve the current status of a specific task ('pending', 'in_progress', 'completed').
     */
    public function getTaskStatus(string $key): ?string
    {
        return $this->tasks[$key]['status'] ?? null;
    }

    /**
     * Mark a task as currently in progress.
     */
    public function markInProgress(string $key): void
    {
        if (isset($this->tasks[$key])) {
            $this->tasks[$key]['status'] = 'in_progress';
        }
    }

    /**
     * Mark a task as completed.
     */
    public function markCompleted(string $key): void
    {
        if (isset($this->tasks[$key])) {
            $this->tasks[$key]['status'] = 'completed';
        }
    }

    /**
     * Render the active tasks into a markdown checklist.
     */
    public function toMarkdown(): string
    {
        $lines = [];
        foreach ($this->tasks as $task) {
            $marker = match ($task['status']) {
                'completed'   => '[x]',
                'in_progress' => '[/]',
                default       => '[ ]',
            };
            $lines[] = "- {$marker} {$task['description']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Synchronize the current markdown checklist to disk.
     */
    public function syncToFile(string $filePath): void
    {
        $directory = dirname($filePath);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($filePath, $this->toMarkdown());
    }

    /**
     * Record a failed approach and its error trace.
     */
    public function recordFailure(string $strategy, string $error): void
    {
        $this->failures[] = [
            'strategy' => $strategy,
            'error'    => $error,
        ];
    }

    /**
     * Determine if a strategy was previously attempted and failed.
     */
    public function hasFailedStrategy(string $strategy): bool
    {
        foreach ($this->failures as $failure) {
            if ($failure['strategy'] === $strategy) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve the list of all recorded failures.
     *
     * @return array<int, array{strategy: string, error: string}>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * Format the scratchpad and failure history into a prompt context payload.
     */
    public function toPromptContext(): string
    {
        $sections = [];

        if (!empty($this->tasks)) {
            $sections[] = "### AGENT WORKING SCRATCHPAD:\n" . $this->toMarkdown();
        }

        if (!empty($this->failures)) {
            $failureLines = ["### PREVIOUSLY FAILED ATTEMPTS (DO NOT REPEAT):"];
            foreach ($this->failures as $failure) {
                $failureLines[] = "Strategy: {$failure['strategy']}\nError: {$failure['error']}";
            }
            $sections[] = implode("\n\n", $failureLines);
        }

        return implode("\n\n", $sections);
    }
}
