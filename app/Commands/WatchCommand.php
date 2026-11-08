<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\AgentContextService;
use App\Services\FileWatcherService;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class WatchCommand extends Command
{
    protected $signature = 'watch
                            {--start : Start the background file watcher}
                            {--stop : Stop the running background file watcher}';

    protected $description = 'Watch workspace directory for file changes and log events dynamically';

    public function handle(FileWatcherService $watcher, AgentContextService $context): int
    {
        $paths = $context->paths();
        $start = (bool) $this->option('start');
        $stop = (bool) $this->option('stop');

        if (!$start && !$stop) {
            $this->error('Please specify either --start or --stop.');
            return Command::INVALID;
        }

        if ($stop) {
            $pid = $watcher->getRunningPid();
            if ($pid === null) {
                $watcher->clearPid();
                $this->info('No active watcher process found.');
                return Command::SUCCESS;
            }

            if ($watcher->stopProcess($pid)) {
                $watcher->clearPid();
                $this->info("File watcher stopped successfully (PID: {$pid}).");
                return Command::SUCCESS;
            }

            $this->error('Failed to stop watcher process.');
            return Command::FAILURE;
        }

        $runningPid = $watcher->getRunningPid();
        if ($runningPid !== null) {
            $this->warn("Watcher is already running (PID: {$runningPid}).");
            return Command::FAILURE;
        }

        $logPath = $paths->getWatchLogPath();
        $relLog = $paths->getRelativeWatchLogPath();
        $logDir = dirname($logPath);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $cmd = sprintf(
            'nohup php %s watch:worker >> %s 2>&1 & echo $!',
            escapeshellarg(base_path('pnc')),
            escapeshellarg($logPath)
        );

        $process = Process::fromShellCommandline($cmd);
        $process->run();

        $pid = (int) trim($process->getOutput());
        if ($pid > 0) {
            $watcher->recordPid($pid);
            $this->info("File watcher started in background (PID: {$pid}).");
            $this->line("Logging changes to: {$relLog}");
            return Command::SUCCESS;
        }

        $this->error("Watcher failed to start. Check {$relLog} for details.");
        return Command::FAILURE;
    }
}
