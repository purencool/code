<?php

declare(strict_types=1);

namespace App\Services\Testing;

use App\Services\AgentContextService;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class TestRunnerService
{
    public function __construct(protected AgentContextService $context)
    {
    }

    /**
     * @param array<int, string> $targetFiles
     */
    public function run(array $targetFiles = []): TestResult
    {
        $workspace = $this->context->getWorkspaceRoot();
        $config = $this->context->load();
        $command = $this->resolveRunnerCommand($workspace, $config, $targetFiles);

        $process = Process::fromShellCommandline($command, $workspace);
        $process->setTimeout(180);
        $process->run();

        return new TestResult(
            successful: $process->isSuccessful(),
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput(),
            command: $command
        );
    }

    /**
     * @param array<string, mixed> $config
     * @param array<int, string> $targetFiles
     */
    public function resolveRunnerCommand(string $workspace, array $config, array $targetFiles = []): string
    {
        $customScript = $config['scripts']['test'] ?? null;
        if (!empty($customScript)) {
            return (string) $customScript;
        }

        $fileArgs = implode(' ', array_map('escapeshellarg', $targetFiles));

        if (File::exists($workspace . '/vendor/bin/pest')) {
            return trim("./vendor/bin/pest " . $fileArgs);
        }

        if (File::exists($workspace . '/vendor/bin/phpunit')) {
            return trim("./vendor/bin/phpunit " . $fileArgs);
        }

        return 'echo "No supported test runner found"';
    }
}
