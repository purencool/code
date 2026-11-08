<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\AgentContextService;
use App\Services\CodebaseIndexService;
use App\Services\Tdd\BatchTaskParser;
use App\Services\Tdd\TaskLogger;
use App\Services\Tdd\TddPromptBuilder;
use App\Services\TddWorkflowService;
use App\Services\Testing\TestRunnerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CodeCommand extends Command
{
    protected $signature = 'code
                            {task? : The issue, feature, or markdown file path (e.g. tasks.md)}
                            {--workspace= : Path to workspace directory}
                            {--e|editor : Open task input directly in your default text editor}
                            {--auto-approve : Skip interactive approval and generate code immediately}';

    protected $description = 'Interactive TDD/CTT code with local Neuron AI automated self-testing';

    public function handle(
        AgentContextService $context,
        CodebaseIndexService $indexer,
        TddWorkflowService $workflow,
        TestRunnerService $testRunner,
        BatchTaskParser $batchParser,
        TaskLogger $taskLogger,
        TddPromptBuilder $promptBuilder
    ): int {
        $workspaceOpt = $this->option('workspace');
        if (!empty($workspaceOpt)) {
            $context->setWorkspaceRoot((string) $workspaceOpt);
        }

        $config = $context->load();
        $this->info("==================================================");
        $this->info("     PncAgent Local TDD/CTT Console (Neuron Loop) ");
        $this->info("==================================================\n");

        $rawInput = $this->argument('task');

        if (!empty($rawInput) && File::exists($context->getWorkspaceRoot() . '/' . $rawInput)) {
            $rawInput = (string) File::get($context->getWorkspaceRoot() . '/' . $rawInput);
        } elseif (!empty($rawInput) && File::exists((string) $rawInput)) {
            $rawInput = (string) File::get((string) $rawInput);
        }

        if (empty($rawInput)) {
            $rawInput = $this->option('editor') ? $this->openInEditor() : $this->captureMultiLineInput();
        }

        if (trim((string) $rawInput) === '') {
            $this->error('No tasks provided. Exiting.');
            return self::FAILURE;
        }

        $taskLogger->logRequest(trim((string) $rawInput));
        $tasks = $batchParser->parse((string) $rawInput);
        $totalTasks = count($tasks);

        foreach ($tasks as $index => $currentTask) {
            $stepNum = $index + 1;
            $meta = $promptBuilder->parseTaskMetadata($currentTask);

            $this->line("==================================================");
            $this->line("<comment>Task [{$stepNum}/{$totalTasks}]</comment>");
            $this->line("==================================================");
            $this->line("Target Type  : <info>" . $meta['target_type'] . "</info>");
            $this->line("Target Test  : <info>" . $meta['target_test'] . "</info>");
            $this->line("Target Class : <info>" . $meta['target_class'] . "</info>");
            if (!empty($meta['dependencies'])) {
                $this->line("Dependencies : <comment>" . implode(', ', $meta['dependencies']) . "</comment>");
            }
            $this->line("--------------------------------------------------");
            $this->line(trim($meta['task']) . "\n");

            if ($meta['target_type'] === 'CTT') {
                $this->handleCttTask($stepNum, $totalTasks, $currentTask, $meta, $context, $workflow, $testRunner, $taskLogger, $config);
            } else {
                $this->handleTddTask($stepNum, $totalTasks, $currentTask, $meta, $context, $indexer, $workflow, $testRunner, $taskLogger, $config);
            }
        }

        $this->info("All {$totalTasks} task(s) processed.");
        return self::SUCCESS;
    }

    /**
     * Executes TDD: Step 1 (Test) -> Step 2 (Review) -> Step 3 (Implementation/Verification Loop)
     *
     * @param array{target_type: string, target_test: string, target_class: string, dependencies: array<int, string>, task: string} $meta
     * @param array<string, mixed> $config
     */
    protected function handleTddTask(
        int $stepNum,
        int $totalTasks,
        string $currentTask,
        array $meta,
        AgentContextService $context,
        CodebaseIndexService $indexer,
        TddWorkflowService $workflow,
        TestRunnerService $testRunner,
        TaskLogger $taskLogger,
        array $config
    ): void {
        $this->line("<comment>[Step 1/3]</comment> Generating test files locally via Neuron AI...");
        $wiringSummary = $indexer->getNeighborhoodContext($currentTask);
        $testPlan = $workflow->generateTests($currentTask, $wiringSummary, $config);

        $targetTestPaths = [];
        foreach ($testPlan['test_files'] as $testFile) {
            $workflow->writeFile($testFile['path'], $testFile['code']);
            $targetTestPaths[] = $testFile['path'];
            $this->info("✔ Written: {$testFile['path']}");
        }

        if (!$this->option('auto-approve')) {
            $choice = $this->choice(
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

        $this->line("\n<comment>[Step 3/3]</comment> Generating and verifying implementation locally...");

        $outcome = $workflow->generateAndTestImplementationLocally(
            $currentTask,
            $testPlan,
            $config,
            $testRunner,
            maxAttempts: 3,
            onRetry: function (int $attempt, int $max, string $errors): void {
                $this->warn("⚠ Attempt {$attempt}/{$max} failed local PHPUnit tests.");
                $this->line("<comment>Feeding error trace back into local Neuron AI thread for repair...</comment>");
            }
        );

        if ($outcome['passed']) {
            $this->info("🎉 Task [{$stepNum}/{$totalTasks}] passed local verification!\n");
            $taskLogger->logTaskOutcome($stepNum, $totalTasks, $currentTask, $targetTestPaths, array_column($outcome['files'], 'path'), 'PASSED');
        } else {
            $this->error("❌ Task [{$stepNum}/{$totalTasks}] failed after 3 attempts.");
            $this->line($outcome['test_output']);
            $taskLogger->logTaskOutcome($stepNum, $totalTasks, $currentTask, $targetTestPaths, array_column($outcome['files'], 'path'), 'FAILED');
        }
    }

    /**
     * Executes CTT: Read Existing Target Class -> Generate Test -> Verify against existing code.
     *
     * @param array{target_type: string, target_test: string, target_class: string, dependencies: array<int, string>, task: string} $meta
     * @param array<string, mixed> $config
     */
    protected function handleCttTask(
        int $stepNum,
        int $totalTasks,
        string $currentTask,
        array $meta,
        AgentContextService $context,
        TddWorkflowService $workflow,
        TestRunnerService $testRunner,
        TaskLogger $taskLogger,
        array $config
    ): void {
        $fullClassPath = $context->getWorkspaceRoot() . '/' . ltrim($meta['target_class'], '/');

        if (!File::exists($fullClassPath)) {
            $this->error("Target Class not found on disk for CTT: {$meta['target_class']}");
            $this->error("CTT requires the production class to already exist before generating tests.");
            return;
        }

        $this->line("<comment>[Step 1/2]</comment> Reading existing class: <info>{$meta['target_class']}</info>");
        $classContent = (string) File::get($fullClassPath);

        $this->line("<comment>[Step 2/2]</comment> Generating test suite for existing code via Neuron AI...");
        $testPlan = $workflow->generateTestsForExistingClass($currentTask, $classContent, $config);

        $targetTestPaths = [];
        foreach ($testPlan['test_files'] as $testFile) {
            $workflow->writeFile($testFile['path'], $testFile['code']);
            $targetTestPaths[] = $testFile['path'];
            $this->info("✔ Written: {$testFile['path']}");
        }

        $this->line("\nExecuting PHPUnit against existing class to verify generated tests...");
        $result = $testRunner->run($targetTestPaths);

        if ($result->successful) {
            $this->info("🎉 CTT Task [{$stepNum}/{$totalTasks}] passed! Existing class satisfies generated tests.\n");
            $taskLogger->logTaskOutcome($stepNum, $totalTasks, $currentTask, $targetTestPaths, [$meta['target_class']], 'PASSED');
        } else {
            $this->warn("⚠ Generated tests encountered failures against the existing class:\n");
            $this->line($result->output . "\n" . $result->errorOutput);
            $taskLogger->logTaskOutcome($stepNum, $totalTasks, $currentTask, $targetTestPaths, [$meta['target_class']], 'FAILED');
        }
    }

    protected function captureMultiLineInput(): string
    {
        $this->line("<comment>Enter tasks (separate multiple tasks with '---', type 'EOF' to finish):</comment>\n");
        $lines = [];
        while (!feof(STDIN)) {
            $line = fgets(STDIN);
            if ($line === false || trim($line) === 'EOF') {
                break;
            }
            if (trim($line) === ':e') {
                return $this->openInEditor();
            }
            $lines[] = rtrim($line, "\r\n");
        }
        return trim(implode("\n", $lines));
    }

    protected function openInEditor(string $initialContent = ''): string
    {
        $editor = getenv('EDITOR') ?: (PHP_OS_FAMILY === 'Windows' ? 'notepad' : 'vim');
        $tempFile = tempnam(sys_get_temp_dir(), 'pnc_task_') . '.md';

        $template = $initialContent !== '' ? $initialContent : "# Enter tasks below and save (:wq).\n# Separate tasks with '---'. Lines starting with '#' are ignored.\n\n";
        file_put_contents($tempFile, $template);

        $descriptors = file_exists('/dev/tty')
            ? [0 => ['file', '/dev/tty', 'r'], 1 => ['file', '/dev/tty', 'w'], 2 => ['file', '/dev/tty', 'w']]
            : [0 => STDIN, 1 => STDOUT, 2 => STDERR];

        $process = proc_open($editor . " " . escapeshellarg($tempFile), $descriptors, $pipes);
        if (is_resource($process)) {
            proc_close($process);
        }

        $content = file_exists($tempFile) ? (string) file_get_contents($tempFile) : '';
        @unlink($tempFile);

        $lines = explode("\n", $content);
        $filtered = array_filter($lines, fn (string $line): bool => !str_starts_with(trim($line), '#'));

        return trim(implode("\n", $filtered));
    }
}
