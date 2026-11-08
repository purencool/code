<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\AgentContextService;
use App\Services\Chat\ClientService;
use Illuminate\Console\Command;
use Throwable;

class ClientCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'client
                            {--model= : Optional model override}
                            {--no-log : Disable logging to .agent/environment/}';

    /**
     * @var string
     */
    protected $description = 'Interactive command chat client connected to agent';

    /**
     * @var array<int, string>
     */
    protected $aliases = ['chat'];

    public function handle(AgentContextService $contextService, ClientService $clientService): int
    {
        $workspace = $contextService->getWorkspaceRoot();
        $logPath = $clientService->getLogFilePath();
        $noLog = (bool) $this->option('no-log');

        $this->info("PncAgent Chat Client");
        $this->line("Workspace: <comment>{$workspace}</comment>");
        if (!$noLog) {
            $this->line("Log file:  <comment>{$logPath}</comment>");
        }
        $this->line("Type <comment>/help</comment> for commands or <comment>/exit</comment> to quit.\n");

        while (true) {
            $input = $this->ask('>');

            if ($input === null || trim($input) === '') {
                continue;
            }

            try {
                $result = $clientService->processInput($input, !$noLog);

                $this->line($result['response']);

                if ($result['should_exit']) {
                    break;
                }
            } catch (Throwable $e) {
                $this->error("Error: " . $e->getMessage());
            }
        }

        return 0;
    }
}
