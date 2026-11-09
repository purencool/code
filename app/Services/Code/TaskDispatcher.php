<?php

declare(strict_types=1);

namespace App\Services\Code;

use App\Services\AgentContextService;
use App\Services\CodebaseIndexService;
use App\Services\CodeWorkflowService;
use App\Services\Testing\TestRunnerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TaskDispatcher
{
    public function __construct(
        private readonly AgentContextService $context,
        private readonly CodebaseIndexService $indexer,
        private readonly CodeWorkflowService $workflow,
        private readonly TestRunnerService $testRunner,
        private readonly TaskLogger $taskLogger,
        private readonly CodeSourceResolver $sourceResolver = new CodeSourceResolver()
    ) {
    }

    public function logRequest(string $rawInput): void
    {
        $this->taskLogger->logRequest(trim($rawInput));
    }

    /**
     * Route task execution based on Target Type metadata.
     *
     * @param array{target_type: string, target_test: string, target_class: string, dependencies: array<int, string>, task: string} $meta
     * @param array<string, mixed> $config
     */
    public function dispatch(
        Command $command,
        int $stepNum,
        int $totalTasks,
        string $currentTask,
        array $meta,
        array $config,
        bool $autoApprove = false
    ): void {
        match ($meta['target_type']) {
            'CTT'   => $this->handleCttTask($command, $stepNum, $totalTasks, $currentTask, $meta, $config),
            'CCC'   => $this->handleCccTask($command, $stepNum, $totalTasks, $currentTask, $meta, $config, $autoApprove),
            'ETC'   => $this->handleEtcTask($command, $stepNum, $totalTasks, $currentTask, $meta, $config),
            default => $this->handleTddTask($command, $stepNum, $totalTasks, $currentTask, $meta, $config, $autoApprove),
        };
    }

    protected function handleTddTask(
        Command $command,
        int $stepNum,
        int $totalTasks,
        string $currentTask,
        array $meta,
        array $config,
        bool $autoApprove
    ): void {
        $command->line("<comment>[Step 1/3]</comment> Generating test files locally via Neuron AI...");
        $wiringSummary = $this->indexer->getNeighborhoodContext($currentTask);
        $testPlan = $this->workflow->generateTests($currentTask, $wiringSummary, $config);

        $targetTestPaths = [];
        foreach ($testPlan['test_files'] as $testFile) {
            $this->workflow->writeFile($testFile['path'], $testFile['code']);
            $targetTestPaths[] = $testFile['path'];
            $command->info("✔ Written: {$testFile['path']}");
        }

        if (!$autoApprove) {
            $choice = $command->choice(
                'Approve tests and begin local implementation loop?',
                ['yes' => 'Yes (Implement)', 'skip' => 'Skip task', 'abort' => 'Abort run'],
                'yes'
            );

            if ($choice === 'skip') {
                return;
            }
            if ($choice === 'abort') {
                exit(0);
            }
        }

        $command->line("\n<comment>[Step 3/3]</comment> Generating and verifying implementation locally...");

        $outcome = $this->workflow->generateAndTestImplementationLocally(
            $currentTask,
            $testPlan,
            $config,
            $this->testRunner,
            maxAttempts: 3,
            onRetry: function (int $attempt, int $max, string $errors) use ($command): void {
                $command->warn("⚠ Attempt {$attempt}/{$max} failed local PHPUnit tests.");
                $command->line("<comment>Feeding error trace back into local Neuron AI thread for repair...</comment>");
            }
        );

        if ($outcome['passed']) {
            $command->info("Task [{$stepNum}/{$totalTasks}] passed local verification!\n");
            $this->taskLogger->logTaskOutcome($stepNum, $totalTasks, $currentTask, $targetTestPaths, array_column($outcome['files'], 'path'), 'PASSED');
        } else {
            $command->error("Task [{$stepNum}/{$totalTasks}] failed after 3 attempts.");
            $command->line($outcome['test_output']);
            $this->taskLogger->logTaskOutcome($stepNum, $totalTasks, $currentTask, $targetTestPaths, array_column($outcome['files'], 'path'), 'FAILED');
        }
    }

    protected function handleCttTask(
        Command $command,
        int $stepNum,
        int $totalTasks,
        string $currentTask,
        array $meta,
        array $config
    ): void {
        $fullClassPath = $this->context->getWorkspaceRoot() . '/' . ltrim($meta['target_class'], '/');

        if (!File::exists($fullClassPath)) {
            $command->error("Target Class not found on disk for CTT: {$meta['target_class']}");
            $command->error("CTT requires the production class to already exist before generating tests.");
            return;
        }

        $command->line("<comment>[Step 1/2]</comment> Reading existing class: <info>{$meta['target_class']}</info>");
        $classContent = (string) File::get($fullClassPath);

        $command->line("<comment>[Step 2/2]</comment> Generating test suite for existing code via Neuron AI...");
        $testPlan = $this->workflow->generateTestsForExistingClass($currentTask, $classContent, $config);

        $targetTestPaths = [];
        foreach ($testPlan['test_files'] as $testFile) {
            $this->workflow->writeFile($testFile['path'], $testFile['code']);
            $targetTestPaths[] = $testFile['path'];
            $command->info("✔ Written: {$testFile['path']}");
        }

        $command->line("\nExecuting PHPUnit against existing class to verify generated tests...");
        $result = $this->testRunner->run($targetTestPaths);

        if ($result->successful) {
            $command->info("CTT Task [{$stepNum}/{$totalTasks}] passed! Existing class satisfies generated tests.\n");
            $this->taskLogger->logTaskOutcome($stepNum, $totalTasks, $currentTask, $targetTestPaths, [$meta['target_class']], 'PASSED');
        } else {
            $command->warn("Generated tests encountered failures against the existing class:\n");
            $command->line($result->output . "\n" . $result->errorOutput);
            $this->taskLogger->logTaskOutcome($stepNum, $totalTasks, $currentTask, $targetTestPaths, [$meta['target_class']], 'FAILED');
        }
    }

    protected function handleCccTask(
        Command $command,
        int $stepNum,
        int $totalTasks,
        string $currentTask,
        array $meta,
        array $config,
        bool $autoApprove
    ): void {
        $command->line("<comment>[Step 1/1]</comment> Generating production class and methods directly via Neuron AI...");

        $wiringSummary = $this->indexer->getNeighborhoodContext($currentTask);
        $generated = $this->workflow->generateDirectClass($currentTask, $wiringSummary, $config);

        $targetPath = $meta['target_class'] ?: $generated['path'];
        $code = $generated['code'];

        if (!$autoApprove) {
            $command->line("\n<comment>--- Preview of {$targetPath} ---</comment>");
            $command->line(substr($code, 0, 500) . (strlen($code) > 500 ? "\n..." : ''));
            $command->line("<comment>---------------------------------</comment>\n");

            $choice = $command->choice(
                "Write generated code to '{$targetPath}'?",
                ['yes' => 'Yes (Write to disk)', 'skip' => 'Skip task', 'abort' => 'Abort run'],
                'yes'
            );

            if ($choice === 'skip') {
                return;
            }
            if ($choice === 'abort') {
                exit(0);
            }
        }

        $this->workflow->writeFile($targetPath, $code);
        $command->info("✔ Written: {$targetPath}");
        $command->info("CCC Task [{$stepNum}/{$totalTasks}] created successfully!\n");

        $this->taskLogger->logTaskOutcome($stepNum, $totalTasks, $currentTask, [], [$targetPath], 'PASSED');
    }

    protected function handleEtcTask(
        Command $command,
        int $stepNum,
        int $totalTasks,
        string $currentTask,
        array $meta,
        array $config
    ): void {
        $resolved = $this->sourceResolver->resolve($this->context->getWorkspaceRoot(), $meta['target_class'] ?? null, $meta['task']);
        $codeToExplain = $resolved['code'];
        $sourceName = $resolved['source_name'];

        if (trim($codeToExplain) === '') {
            $command->error("No code found to explain. Provide a valid 'Target Class' on disk or paste code inside the task.");
            return;
        }

        $command->line("<comment>[Step 1/1]</comment> Analyzing code from: <info>{$sourceName}</info>");
        $explanation = $this->workflow->explainCode($codeToExplain, $config);

        $command->line("\n==================================================");
        $command->line("<info>Code Explanation ({$sourceName})</info>");
        $command->line("==================================================");
        $command->line($explanation . "\n");

        $target = !empty($meta['target_class']) ? [$meta['target_class']] : [];
        $this->taskLogger->logTaskOutcome($stepNum, $totalTasks, $currentTask, [], $target, 'PASSED');
    }
}
