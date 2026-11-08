<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\AI\AIProviderFactory;
use App\Services\Tdd\TddPromptBuilder;
use App\Services\Tdd\TddResponseParser;
use App\Services\Testing\TestRunnerService;
use App\Services\Testing\TestResult;
use Illuminate\Support\Facades\File;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;
use RuntimeException;

class TddWorkflowService
{
    protected AIProviderFactory $providerFactory;
    protected TddPromptBuilder $promptBuilder;
    protected TddResponseParser $parser;

    public function __construct(
        private readonly AgentContextService $context,
        ?AIProviderFactory $providerFactory = null,
        ?TddPromptBuilder $promptBuilder = null,
        ?TddResponseParser $parser = null,
        private ?AIProviderInterface $provider = null,
    ) {
        $this->providerFactory = $providerFactory ?? new AIProviderFactory($context);
        $this->promptBuilder = $promptBuilder ?? new TddPromptBuilder();
        $this->parser = $parser ?? new TddResponseParser();
    }

    public function setProvider(AIProviderInterface $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * TDD: Generate test suite from task requirements.
     *
     * @param array<string, mixed> $config
     * @return array{explanation: string, test_files: array<int, array{path: string, code: string}>}
     */
    public function generateTests(string $task, string $wiringContext, array $config): array
    {
        $meta = $this->promptBuilder->parseTaskMetadata($task);
        $prompt = $this->promptBuilder->buildTestGenerationPrompt($task, $wiringContext, $config);

        return $this->executeTestGenerationPrompt($prompt, $task, $meta);
    }

    /**
     * CTT: Generate test suite for existing production class source code.
     *
     * @param array<string, mixed> $config
     * @return array{explanation: string, test_files: array<int, array{path: string, code: string}>}
     */
    public function generateTestsForExistingClass(string $task, string $classContent, array $config): array
    {
        $meta = $this->promptBuilder->parseTaskMetadata($task);
        $prompt = $this->promptBuilder->buildCttTestPrompt($task, $classContent, $config);

        return $this->executeTestGenerationPrompt($prompt, $task, $meta);
    }

    /**
     * @param array{target_type: string, target_test: string, target_class: string, dependencies: array<int, string>, task: string} $meta
     * @return array{explanation: string, test_files: array<int, array{path: string, code: string}>}
     */
    protected function executeTestGenerationPrompt(string $prompt, string $task, array $meta): array
    {
        $provider = $this->providerFactory->make($this->provider);
        $agent = Agent::make()->setAiProvider($provider);
        $agent->setThreadId(uniqid('tdd_test_', true));

        $response = $agent->chat(new UserMessage($prompt));
        $content = $this->extractResponseContent($response);

        $this->logExchange('generateTests', $prompt, $content);

        $parsed = $this->parser->parseTestPlan($content, $task);

        if (empty($parsed['test_files'])) {
            throw new RuntimeException("Failed to parse test generation plan from AI response:\n\n" . $content);
        }

        if (count($parsed['test_files']) === 1 && !empty($meta['target_test'])) {
            $parsed['test_files'][0]['path'] = $meta['target_test'];
        }

        foreach ($parsed['test_files'] as $tf) {
            $this->validateGeneratedCode($tf['code'], $tf['path'], $meta);
        }

        return $parsed;
    }

    /**
     * TDD Step 3: Implement, locally test, and automatically repair until green.
     *
     * @param array{explanation?: string, test_files: array<int, array{path: string, code: string}>} $testPlan
     * @param array<string, mixed> $config
     * @param callable(int, int, string): void|null $onRetry
     * @return array{files: array<int, array{path: string, content: string}>, passed: bool, test_output: string}
     */
    public function generateAndTestImplementationLocally(
        string $task,
        array $testPlan,
        array $config,
        TestRunnerService $testRunner,
        int $maxAttempts = 3,
        ?callable $onRetry = null
    ): array {
        $meta = $this->promptBuilder->parseTaskMetadata($task);
        $testPaths = array_map(fn (array $tf): string => $tf['path'], $testPlan['test_files']);

        $provider = $this->providerFactory->make($this->provider);
        $agent = Agent::make()->setAiProvider($provider);
        $agent->setThreadId(uniqid('tdd_repair_', true));

        $currentPrompt = $this->promptBuilder->buildImplementationPrompt($task, $testPlan, $config);
        $lastFiles = [];
        $lastResult = null;
        $lastErrorHash = null;

        $targetClassName = $this->promptBuilder->resolveClassNameFromPath($meta['target_class'], 'Service');
        $targetNamespace = $this->promptBuilder->resolveNamespaceFromPath($meta['target_class'], 'App\\Services');

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = $agent->chat(new UserMessage($currentPrompt));
            $content = $this->extractResponseContent($response);

            $this->logExchange("generateImplementation_attempt_{$attempt}", $currentPrompt, $content);

            $parsed = $this->parser->parseImplementation($content, $task);

            if (empty($parsed['files'])) {
                $currentPrompt = "Syntax Error: Your previous response did not contain a valid PHP markdown code block. Return the code wrapped in ```php ... ```.";
                continue;
            }

            if (count($parsed['files']) === 1 && !empty($meta['target_class'])) {
                $parsed['files'][0]['path'] = $meta['target_class'];
            }

            // CRITICAL CHECK: Verify that the model wrote production code, NOT another test file
            $implContent = $parsed['files'][0]['content'];
            $validationError = $this->validateProductionCode($implContent, $targetClassName, $targetNamespace);

            if ($validationError !== null) {
                if ($onRetry !== null) {
                    $onRetry($attempt, $maxAttempts, $validationError);
                }

                $currentPrompt = "CRITICAL ARCHITECTURAL REJECTION:\n"
                    . "{$validationError}\n\n"
                    . "You wrote a PHPUnit test file instead of the actual production class!\n"
                    . "You MUST output the production class satisfying the tests.\n"
                    . "- Namespace: {$targetNamespace}\n"
                    . "- Class Name: {$targetClassName}\n"
                    . "- DO NOT extend TestCase. DO NOT use PHPUnit.\n"
                    . "- Implement the methods tested: constructor, cancelSubscription, getCancellationDate, etc.\n"
                    . "Output ONLY the production class inside ```php ... ```.";
                continue;
            }

            foreach ($parsed['files'] as $f) {
                $this->writeFile($f['path'], $f['content']);
            }
            $lastFiles = $parsed['files'];

            /** @var TestResult $result */
            $result = $testRunner->run($testPaths);
            $lastResult = $result;

            if ($result->successful) {
                return [
                    'files'       => $lastFiles,
                    'passed'      => true,
                    'test_output' => $result->output,
                ];
            }

            $currentErrorHash = md5($result->output . $result->errorOutput);
            if ($currentErrorHash === $lastErrorHash) {
                break;
            }
            $lastErrorHash = $currentErrorHash;

            if ($onRetry !== null) {
                $onRetry($attempt, $maxAttempts, $result->output . "\n" . $result->errorOutput);
            }

            $currentPrompt = "PHPUnit test execution failed on attempt {$attempt}/{$maxAttempts}.\n\n"
                . "### TEST ERROR OUTPUT:\n"
                . $result->output . "\n"
                . $result->errorOutput . "\n\n"
                . "Analyze the failure above, resolve all errors or missing methods in '{$targetClassName}', and output the updated production PHP class wrapped in ```php ... ```.";
        }

        return [
            'files'       => $lastFiles,
            'passed'      => false,
            'test_output' => ($lastResult ? $lastResult->output . "\n" . $lastResult->errorOutput : 'No execution output.'),
        ];
    }

    /**
     * Validates that generated code is actual production logic, not a test file.
     */
    public function validateProductionCode(string $code, string $expectedClass, string $expectedNamespace): ?string
    {
        if (str_contains($code, 'extends TestCase') || str_contains($code, 'PHPUnit\\Framework\\TestCase')) {
            return "Generated code extends 'TestCase' or imports PHPUnit. Production code must never be a test.";
        }

        if (preg_match('/class\s+([A-Za-z0-9_]+Test)\b/', $code, $m)) {
            return "Generated class '{$m[1]}' ends in 'Test'. Production class must be named '{$expectedClass}'.";
        }

        if (!preg_match('/\bclass\s+' . preg_quote($expectedClass, '/') . '\b/', $code)) {
            return "Generated code does not declare class '{$expectedClass}'.";
        }

        if (str_contains($code, 'namespace Tests\\')) {
            return "Generated code uses a test namespace 'namespace Tests\\...'. Production namespace must be '{$expectedNamespace}'.";
        }

        return null;
    }

    public function writeFile(string $relativePath, string $content): void
    {
        $fullPath = $this->context->getWorkspaceRoot() . '/' . ltrim($relativePath, '/');
        $directory = dirname($fullPath);

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $content = preg_replace("/\?>\s*$/", "", trim($content));
        $content = preg_replace("/^[+-]\s{2,}/m", "        ", $content);
        $content = preg_replace("/(\n[ \t]*\})\n+([ \t]*\/\*\*)/", "$1\n\n$2", $content);
        $content = rtrim($content) . "\n";

        File::put($fullPath, $content);
    }

    /**
     * @param array{target_type?: string, target_test?: ?string, target_class?: ?string, dependencies?: array<int, string>, task?: string} $meta
     */
    public function validateGeneratedCode(string $code, string $filePath, array $meta = []): void
    {
        if (preg_match('/<[A-Za-z0-9_]+>/', $code, $matches)) {
            throw new RuntimeException("Model returned un-substituted placeholder '{$matches[0]}' in {$filePath}.");
        }

        if (preg_match('/\$this->assertTrue\(true\);/', $code) && !empty($meta['dependencies'])) {
            throw new RuntimeException("Model generated a stub test ('assertTrue(true)') instead of mocking dependencies for {$filePath}.");
        }
    }

    protected function extractResponseContent(mixed $response): string
    {
        if (is_string($response)) {
            return $response;
        }

        if (is_object($response)) {
            if (method_exists($response, 'getMessage')) {
                $msg = $response->getMessage();
                if (is_object($msg) && method_exists($msg, 'getContent')) {
                    return (string) $msg->getContent();
                }
                if (is_string($msg)) {
                    return $msg;
                }
            }

            if (method_exists($response, 'getContent')) {
                return (string) $response->getContent();
            }

            if (method_exists($response, '__toString')) {
                return (string) $response;
            }
        }

        return '';
    }

    protected function logExchange(string $action, string $prompt, string $response): void
    {
        $logDir = $this->context->paths()->getTemplateDir();
        $logPath = $logDir . '/ai_debug.log';

        if (!File::isDirectory($logDir)) {
            File::makeDirectory($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $divider = str_repeat('=', 60);

        $entry = "\n" . $divider . "\n"
            . "[{$timestamp}] Action: {$action}\n"
            . $divider . "\n"
            . ">>> PROMPT:\n{$prompt}\n\n"
            . "<<< RAW RESPONSE:\n{$response}\n\n";

        File::append($logPath, $entry);
    }
}
