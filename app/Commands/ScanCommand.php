<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\AgentContextService;
use App\Services\CodebaseIndexService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ScanCommand extends Command
{
    protected $signature = 'scan
                            {file? : Inspect wiring for an individual file}
                            {--file= : Inspect wiring for an individual file}
                            {--workspace= : Path to workspace directory}';

    protected $description = 'Scan and index workspace dependencies, declarations, and architectural wiring';

    public function handle(CodebaseIndexService $indexer, AgentContextService $context): int
    {
        $workspaceOpt = $this->option('workspace');
        if (!empty($workspaceOpt)) {
            $context->setWorkspaceRoot((string) $workspaceOpt);
        }

        $paths = $context->paths();
        $configFile = $paths->getConfigPath();

        if (!File::exists($configFile)) {
            $this->error("Workspace is not initialized. Run 'pncagent init' first.");
            return Command::FAILURE;
        }

        $workspace = $paths->getWorkspaceRoot();
        $targetFile = $this->argument('file') ?? $this->option('file');

        if ($targetFile !== null && $targetFile !== '') {
            $fullPath = str_starts_with($targetFile, '/') ? $targetFile : $workspace . '/' . $targetFile;
            if (!File::exists($fullPath)) {
                $this->error("File not found: {$targetFile}");
                return Command::FAILURE;
            }

            $this->info("Inspecting wiring for: {$targetFile}");
            $wiring = $indexer->inspectFile($targetFile);

            $this->table(['Property', 'Value'], [
                ['File', $targetFile],
                ['Path', $targetFile],
                ['Size', filesize($fullPath) . ' bytes'],
                ['Classes', !empty($wiring['classes']) ? implode(', ', $wiring['classes']) : 'None'],
                ['Dependencies', !empty($wiring['dependencies']) ? implode(', ', $wiring['dependencies']) : 'None'],
            ]);

            return Command::SUCCESS;
        }

        $this->info("Scanning workspace: {$workspace}");
        $graph = $indexer->scan($workspace);

        $this->info('Index built successfully.');

        $this->table(['Metric', 'Count'], [
            ['Scanned At', $graph['scanned_at'] ?? 'now'],
            ['Cache Output', $paths->getRelativeCachePath('wiring.json')],
        ]);

        return Command::SUCCESS;
    }
}
