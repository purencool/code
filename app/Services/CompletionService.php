<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class CompletionService
{
    protected AgentContextService $context;

    public function __construct(AgentContextService $context)
    {
        $this->context = $context;
    }

    /**
     * Get path to daily autocomplete log.
     */
    public function getLogFile(): string
    {
        $logDir = $this->context->paths()->getLogsDir();
        if (!File::isDirectory($logDir)) {
            File::makeDirectory($logDir, 0755, true);
        }

        return $this->context->paths()->getLogsDir(sprintf('code-%s.log', date('Y-m-d')));
    }

    /**
     * Record completion to daily log.
     */
    public function logCompletion(string $prompt, string $completion): void
    {
        $logFile = $this->getLogFile();
        $entry = sprintf("[%s]\nPROMPT: %s\nCOMPLETION: %s\n---\n", date('Y-m-d H:i:s'), $prompt, $completion);
        File::append($logFile, $entry);
    }
}
