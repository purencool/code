<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\AgentContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class StreamCommand extends Command
{
    protected $signature = 'stream {--tail=10 : Number of lines to tail initially}';
    protected $description = 'Stream AI suggestions live from daily code logs';

    public function handle(AgentContextService $context): int
    {
        $paths = $context->paths();
        $logDir = $paths->getLogsDir();
        $todayLog = $paths->getLogsDir(sprintf('code-%s.log', date('Y-m-d')));

        if (!File::exists($todayLog)) {
            $this->warn("No log file found for today at {$paths->getRelativePath('logs/' . basename($todayLog))}.");
            return Command::SUCCESS;
        }

        $this->info("Streaming suggestions from {$paths->getRelativePath('logs/' . basename($todayLog))}...");
        $handle = fopen($todayLog, 'r');
        if (!$handle) {
            $this->error('Failed to open log file.');
            return Command::FAILURE;
        }

        fseek($handle, 0, SEEK_END);
        while (true) {
            $line = fgets($handle);
            if ($line !== false) {
                $this->output->write($line);
            } else {
                usleep(250000);
            }
        }

        return Command::SUCCESS;
    }
}
