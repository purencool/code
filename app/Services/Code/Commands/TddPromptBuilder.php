<?php

declare(strict_types=1);

namespace App\Services\Code\Commands;

use App\Services\Code\TestGenerationPromptBuilder;
use App\Services\Code\Commands\CttTestPromptBuilder;
use App\Services\Code\ImplementationPromptBuilder;
use App\Services\Code\PathNamespaceResolver;

class TddPromptBuilder
{
    public function __construct(
        private readonly TestGenerationPromptBuilder $testGenBuilder = new TestGenerationPromptBuilder(),
        private readonly CttTestPromptBuilder $cttBuilder = new CttTestPromptBuilder(),
        private readonly ImplementationPromptBuilder $implementationBuilder = new ImplementationPromptBuilder(),
        private readonly TaskMetadataParser $metadataParser = new TaskMetadataParser(),
        private readonly PathNamespaceResolver $pathResolver = new PathNamespaceResolver(),
    ) {
    }

    /**
     * Step 1 (TDD): Generate test from user requirements.
     *
     * @param array<string, mixed> $config
     */
    public function buildTestGenerationPrompt(string $task, string $wiringContext, array $config): string
    {
        return $this->testGenBuilder->build($task, $wiringContext, $config);
    }

    /**
     * Step 1 (CTT): Generate tests for an existing production class file.
     *
     * @param array<string, mixed> $config
     */
    public function buildCttTestPrompt(string $task, string $classContent, array $config): string
    {
        return $this->cttBuilder->build($task, $classContent, $config);
    }

    /**
     * Step 3 (TDD): Implement production class matching approved test plan.
     *
     * @param array{explanation?: string, test_files: array<int, array{path: string, code: string}>} $testPlan
     * @param array<string, mixed> $config
     */
    public function buildImplementationPrompt(string $task, array $testPlan, array $config): string
    {
        return $this->implementationBuilder->build($task, $testPlan, $config);
    }

    /**
     * Backward-compatible delegation to TaskMetadataParser.
     *
     * @return array{target_type: string, target_test: string, target_class: string, dependencies: array<int, string>, task: string}
     */
    public function parseTaskMetadata(string $rawTask): array
    {
        return $this->metadataParser->parse($rawTask);
    }

    /**
     * Backward-compatible delegation to PathNamespaceResolver.
     */
    public function resolveNamespaceFromPath(string $path, string $default): string
    {
        return $this->pathResolver->resolveNamespaceFromPath($path, $default);
    }

    /**
     * Backward-compatible delegation to PathNamespaceResolver.
     */
    public function resolveClassNameFromPath(string $path, string $default): string
    {
        return $this->pathResolver->resolveClassNameFromPath($path, $default);
    }
}
