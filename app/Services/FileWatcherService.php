<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class FileWatcherService
{
    protected AgentPathService $paths;

    public function __construct(?AgentPathService $paths = null)
    {
        $this->paths = $paths ?? app(AgentPathService::class);
    }

    /**
     * Check whether a given path should be ignored by the file watcher.
     */
    public function shouldIgnore(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $agentDir = $this->paths->getDirectoryName();

        if (str_starts_with($normalized, $agentDir . '/') || $normalized === $agentDir) {
            return true;
        }

        $ignorePatterns = [
            'vendor/',
            'node_modules/',
            '.git/',
            'storage/',
        ];

        foreach ($ignorePatterns as $pattern) {
            if (str_contains($normalized, $pattern) || str_starts_with($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function getPidPath(?string $template = null): string
    {
        return $this->paths->getPidPath($template);
    }

    public function getLogPath(?string $template = null): string
    {
        return $this->paths->getWatchLogPath($template);
    }

    /**
     * Get the active watcher process ID, or null if not currently running.
     */
    public function getRunningPid(?string $template = null): ?int
    {
        $pidPath = $this->getPidPath($template);
        if (!File::exists($pidPath)) {
            return null;
        }

        $pid = (int) trim((string) File::get($pidPath));
        if ($pid <= 0) {
            return null;
        }

        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0) ? $pid : null;
        }

        return $pid;
    }

    /**
     * Terminate a watcher process by PID.
     */
    public function stopProcess(int $pid): bool
    {
        if ($pid > 0 && function_exists('posix_kill')) {
            @posix_kill($pid, SIGTERM);
        }

        return true;
    }

    /**
     * Remove the watcher PID lockfile.
     */
    public function clearPid(?string $template = null): void
    {
        $pidPath = $this->getPidPath($template);
        if (File::exists($pidPath)) {
            @unlink($pidPath);
        }
    }

    public function isRunning(?string $template = null): bool
    {
        return $this->getRunningPid($template) !== null;
    }

    public function stop(?string $template = null): bool
    {
        $pidPath = $this->getPidPath($template);
        if (!File::exists($pidPath)) {
            return false;
        }

        $pid = (int) trim((string) File::get($pidPath));
        if ($pid > 0) {
            $this->stopProcess($pid);
        }

        $this->clearPid($template);
        return true;
    }

    public function recordPid(int $pid, ?string $template = null): void
    {
        $pidPath = $this->getPidPath($template);
        $dir = dirname($pidPath);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($pidPath, (string) $pid);
    }

    public function logEvent(string $event, string $file, ?string $template = null): void
    {
        $logPath = $this->getLogPath($template);
        $dir = dirname($logPath);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $line = sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), strtoupper($event), $file);
        File::append($logPath, $line);
    }
}
