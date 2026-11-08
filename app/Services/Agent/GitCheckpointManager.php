<?php

declare(strict_types=1);

namespace App\Services\Agent;

use Symfony\Component\Process\Process;

class GitCheckpointManager
{
    /**
     * Stored checkpoints mapped by unique identifier.
     *
     * @var array<string, array{head: string, time: int}>
     */
    protected array $checkpoints = [];

    /**
     * Determine if the working tree is clean (no uncommitted or untracked changes).
     */
    public function isClean(string $repoPath): bool
    {
        return empty($this->getDirtyFiles($repoPath));
    }

    /**
     * Get a list of dirty files with their git status codes (e.g. 'M', 'A', '??').
     *
     * @return array<string, string> Keyed by relative file path.
     */
    public function getDirtyFiles(string $repoPath): array
    {
        $process = new Process(['git', '-C', $repoPath, 'status', '--porcelain']);
        $process->run();

        $output = $process->getOutput();
        if (trim($output) === '') {
            return [];
        }

        // Split preserving exact column indices on each line
        $lines = preg_split('/\r?\n/', $output);
        $dirtyFiles = [];

        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n");
            if (strlen($line) < 4) {
                continue;
            }

            // Columns 0 and 1 represent staged and unstaged status flags
            $statusCode = trim(substr($line, 0, 2));
            $filePath = trim(substr($line, 3));
            $filePath = trim($filePath, '"');

            // Normalize git rename format "old -> new" to target path
            if (str_contains($filePath, ' -> ')) {
                $parts = explode(' -> ', $filePath);
                $filePath = trim($parts[1], " \t\n\r\0\x0B\"");
            }

            $dirtyFiles[$filePath] = $statusCode;
        }

        return $dirtyFiles;
    }

    /**
     * Create a checkpoint snapshot of the current HEAD commit state.
     */
    public function createCheckpoint(string $repoPath, string $label = ''): string
    {
        $process = new Process(['git', '-C', $repoPath, 'rev-parse', 'HEAD']);
        $process->run();

        $head = trim($process->getOutput());
        $checkpointId = $label !== '' ? $label . '_' . uniqid() : uniqid('ckpt_');

        $this->checkpoints[$checkpointId] = [
            'head' => $head,
            'time' => time(),
        ];

        return $checkpointId;
    }

    /**
     * Rollback the repository to the specified checkpoint (or HEAD), purging untracked files.
     */
    public function rollback(string $repoPath, ?string $checkpointId = null): void
    {
        $targetCommit = 'HEAD';
        if ($checkpointId !== null && isset($this->checkpoints[$checkpointId])) {
            $targetCommit = $this->checkpoints[$checkpointId]['head'];
        }

        // 1. Reset tracked files back to the checkpoint commit
        $resetProcess = new Process(['git', '-C', $repoPath, 'reset', '--hard', $targetCommit]);
        $resetProcess->run();

        // 2. Remove untracked files and directories created during the run
        $cleanProcess = new Process(['git', '-C', $repoPath, 'clean', '-fd']);
        $cleanProcess->run();
    }
}
