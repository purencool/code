<?php

declare(strict_types=1);

namespace App\Services\Code;

use App\Services\Code\Commands\TaskMetadataParser;

class TestGenerationPromptBuilder
{
    public function __construct(
        private readonly TaskMetadataParser $metadataParser = new TaskMetadataParser(),
        private readonly PathNamespaceResolver $pathResolver = new PathNamespaceResolver(),
        private readonly DependencyInstructionBuilder $dependencyBuilder = new DependencyInstructionBuilder(),
    ) {
    }

    /**
     * Step 1 (TDD): Generate test from user requirements.
     *
     * @param array<string, mixed> $config
     */
    public function build(string $task, string $wiringContext, array $config): string
    {
        $meta = $this->metadataParser->parse($task);
        $rules = $this->formatRules($config);

        $testPath = $meta['target_test'];
        $testNs = $this->pathResolver->resolveNamespaceFromPath($testPath, 'Tests\\Unit');
        $testClass = $this->pathResolver->resolveClassNameFromPath($testPath, 'GeneratedTest');

        $implPath = $meta['target_class'];
        $implNs = $this->pathResolver->resolveNamespaceFromPath($implPath, 'App\\Services');
        $implClass = $this->pathResolver->resolveClassNameFromPath($implPath, 'GeneratedClass');
        $implFqcn = $implNs . '\\' . $implClass;

        $depInstructions = $this->dependencyBuilder->buildTestMockInstructions($meta['dependencies'], $implClass);

        return "SYSTEM INSTRUCTION:\n"
            . "You are an automated code generator executing Step 1 (Test Generation) of TDD.\n"
            . "Write a complete, executable PHPUnit test suite implementing all user requirements.\n\n"
            . "### TARGET ARCHITECTURE (STRICT):\n"
            . "- Target Test File: {$testPath}\n"
            . "- Target Test Namespace: {$testNs}\n"
            . "- Target Test Class Name: {$testClass}\n"
            . "- Target Production Class: {$implFqcn}\n"
            . "- Target Production File: {$implPath}\n\n"
            . $depInstructions
            . "### USER REQUIREMENTS:\n"
            . $meta['task'] . "\n\n"
            . "### ARCHITECTURAL RULES:\n"
            . $rules . "\n\n"
            . "### CODEBASE WIRING:\n"
            . $wiringContext . "\n\n"
            . "### DOCUMENTATION & FORMATTING REQUIREMENTS:\n"
            . "- MUST add a class PHPDoc block with '@covers \\{$implFqcn}'.\n"
            . "- MUST add a descriptive PHPDoc comment above every test method explaining what it tests.\n"
            . "- Class MUST be named exactly '{$testClass}' and extend 'PHPUnit\\Framework\\TestCase'.\n"
            . "- MUST instantiate and test '{$implClass}' (imported via 'use {$implFqcn};').\n\n"
            . "### OUTPUT FORMAT:\n"
            . "Output ONLY the complete PHP test file inside a single markdown code block.\n"
            . "Do NOT return JSON. Do NOT explain your code outside the block.\n\n"
            . "Example format:\n"
            . "```php\n"
            . "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$testNs};\n\n"
            . "use {$implFqcn};\n"
            . "use PHPUnit\\Framework\\TestCase;\n\n"
            . "/**\n"
            . " * @covers \\{$implFqcn}\n"
            . " */\n"
            . "class {$testClass} extends TestCase\n"
            . "{\n"
            . "    // Tests...\n"
            . "}\n"
            . "```";
    }

    /**
     * @param array<string, mixed> $config
     */
    private function formatRules(array $config): string
    {
        return implode("\n", array_map(fn (string $r): string => "- {$r}", $config['agent_rules'] ?? []));
    }
}
