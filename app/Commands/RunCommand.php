<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\AgentContextService;
use App\Services\AgentPathService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class RunCommand extends Command
{
    protected $signature = 'run {script : The key of the script to execute from the agent config}';
    protected $description = 'Execute a predefined script from the agent workspace configuration';

    public function handle(AgentContextService $context): int
    {
        $paths = $context->paths();
        $configFile = $this->locateConfig($paths);

        if ($configFile === null) {
            $this->error("No {$paths->getRelativePath('config.json')} file found in this directory or any parent directories.");
            return Command::FAILURE;
        }

        $config = json_decode((string) File::get($configFile), true);
        if (!is_array($config)) {
            $this->error("Failed to parse {$paths->getRelativePath('config.json')}.");
            return Command::FAILURE;
        }

        $scriptKey = $this->argument('script');
        $scripts = $config['scripts'] ?? [];

        if (!isset($scripts[$scriptKey])) {
            $this->error("Script '{$scriptKey}' is not defined in {$paths->getRelativePath('config.json')}.");
            return Command::FAILURE;
        }

        $command = $scripts[$scriptKey];
        $this->info("Executing: {$command}");

        $process = Process::fromShellCommandline($command, dirname(dirname($configFile)));
        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());

        $exitCode = $process->run(function ($type, $buffer): void {
            $this->output->write($buffer);
        });

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    protected function locateConfig(AgentPathService $paths): ?string
    {
        $agentDir = $paths->getDirectoryName();
        $dir = $paths->getWorkspaceRoot();

        while ($dir !== '/' && !empty($dir)) {
            $candidate = $dir . '/' . $agentDir . '/config.json';
            if (File::exists($candidate)) {
                return $candidate;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        $baseCandidate = base_path($agentDir . '/config.json');
        if (File::exists($baseCandidate)) {
            return $baseCandidate;
        }

        return null;
    }
}
