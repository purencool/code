<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class DeadCodeCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'audit:dead-code
                            {path? : Relative or absolute path to the directory to inspect (default: app)}
                            {--raw : Output raw Psalm stdout directly}
                            {--find-unused-vars : Also report unused local variables}
                            {--no-fail : Exit with status 0 even if dead code elements are detected}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Scan the codebase for unused classes, methods, properties, and dead code';

    public function handle(): int
    {
        $targetPath = $this->argument('path') ?? 'app';
        $fullPath = str_starts_with($targetPath, '/') ? $targetPath : base_path($targetPath);

        if (!File::exists($fullPath)) {
            $this->error("Target path does not exist: {$fullPath}");
            return Command::FAILURE;
        }

        $psalmBinary = base_path('vendor/bin/psalm');
        if (!File::exists($psalmBinary)) {
            $this->error("Psalm binary not found at {$psalmBinary}. Run: composer require --dev vimeo/psalm");
            return Command::FAILURE;
        }

        $this->ensureConfigExists($targetPath);

        $this->info("Scanning for dead code in [{$targetPath}]...");

        $command = [
            $psalmBinary,
            '--find-dead-code',
            '--no-cache',
        ];

        if ($this->option('find-unused-vars')) {
            $command[] = '--find-unused-variables';
        }

        if (!$this->option('raw')) {
            $command[] = '--output-format=json';
        }

        $process = new Process($command, base_path());
        $process->setTimeout(null);

        try {
            $process->run();
        } catch (Throwable $e) {
            $this->error("Failed to run Psalm: {$e->getMessage()}");
            return Command::FAILURE;
        }

        $output = $process->getOutput();
        $errorOutput = $process->getErrorOutput();

        if ($this->option('raw')) {
            $this->line($output ?: $errorOutput);
            if ($this->option('no-fail')) {
                return Command::SUCCESS;
            }
            return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
        }

        return $this->renderStructuredResults($output, $errorOutput, $process->getExitCode() ?? 0);
    }

    /**
     * Format Psalm JSON output into an actionable terminal table.
     */
    protected function renderStructuredResults(string $jsonOutput, string $stderr, int $exitCode): int
    {
        $issues = json_decode(trim($jsonOutput), true);

        if (!is_array($issues)) {
            if ($exitCode === 0 || empty(trim($stderr . $jsonOutput))) {
                $this->info('No dead or redundant code detected.');
                return Command::SUCCESS;
            }

            $this->warn('Raw Psalm output:');
            $this->line($stderr ?: $jsonOutput);
            return $this->option('no-fail') ? Command::SUCCESS : Command::FAILURE;
        }

        $deadCodeTypes = [
            'UnusedClass',
            'UnusedMethod',
            'PossiblyUnusedMethod',
            'UnusedProperty',
            'PossiblyUnusedProperty',
            'UnusedVariable',
            'PossiblyUnusedReturnValue',
        ];

        $deadItems = array_filter($issues, function (array $issue) use ($deadCodeTypes): bool {
            return in_array($issue['type'] ?? '', $deadCodeTypes, true);
        });

        if (empty($deadItems)) {
            $this->info('No dead or unused symbols detected.');
            return Command::SUCCESS;
        }

        $tableRows = [];
        foreach ($deadItems as $item) {
            $tableRows[] = [
                $item['type'] ?? 'DeadCode',
                $item['file_name'] ?? 'unknown',
                (string) ($item['line_from'] ?? '?'),
                trim($item['message'] ?? ''),
            ];
        }

        $this->warn(sprintf('Found %d dead or unreferenced code elements:', count($tableRows)));
        $this->table(['Issue Type', 'File', 'Line', 'Details'], $tableRows);

        return $this->option('no-fail') ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Generate a minimal psalm.xml if the project does not already have one.
     */
    protected function ensureConfigExists(string $targetDirectory): void
    {
        $configFile = base_path('psalm.xml');
        if (File::exists($configFile)) {
            return;
        }

        $xml = <<<XML
<?xml version="1.0"?>
<psalm
    errorLevel="4"
    resolveFromConfigFile="true"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns="https://getpsalm.org/schema/config"
    xsi:schemaLocation="https://getpsalm.org/schema/config vendor/vimeo/psalm/config.xsd"
    findUnusedCode="true"
>
    <projectFiles>
        <directory name="{$targetDirectory}" />
        <ignoreFiles>
            <directory name="vendor" />
        </ignoreFiles>
    </projectFiles>
</psalm>
XML;

        File::put($configFile, $xml);
        $this->comment("Generated baseline configuration in psalm.xml");
    }
}
