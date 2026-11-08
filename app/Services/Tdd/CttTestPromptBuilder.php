<?php

declare(strict_types=1);

namespace App\Services\Tdd;

class CttTestPromptBuilder
{
    public function __construct(
        private readonly TaskMetadataParser $metadataParser = new TaskMetadataParser(),
        private readonly PathNamespaceResolver $pathResolver = new PathNamespaceResolver(),
    ) {
    }

    /**
     * Step 1 (CTT): Generate tests for an existing production class file.
     *
     * @param array<string, mixed> $config
     */
    public function build(string $task, string $classContent, array $config): string
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

        return "SYSTEM INSTRUCTION:\n"
            . "You are an automated test engineer executing CTT (Code-To-Test characterization testing).\n"
            . "Inspect the existing PHP production class provided below and generate a complete PHPUnit test suite.\n\n"
            . "### TARGET ARCHITECTURE (STRICT):\n"
            . "- Target Test File: {$testPath}\n"
            . "- Target Test Namespace: {$testNs}\n"
            . "- Target Test Class Name: {$testClass}\n"
            . "- Target Production Class: {$implFqcn}\n"
            . "- Target Production File: {$implPath}\n\n"
            . "### EXISTING PRODUCTION SOURCE CODE:\n"
            . "```php\n"
            . $classContent . "\n"
            . "```\n\n"
            . "### ADDITIONAL TEST REQUIREMENTS:\n"
            . ($meta['task'] !== '' ? $meta['task'] : 'Cover all public methods, return values, exceptions, and branches.') . "\n\n"
            . "### ARCHITECTURAL RULES:\n"
            . $rules . "\n\n"
            . "### DOCUMENTATION & TESTING RULES:\n"
            . "- Inspect constructor parameters: mock interfaces or classes as needed using '\$this->createMock()'.\n"
            . "- MUST add a class PHPDoc block with '@covers \\{$implFqcn}'.\n"
            . "- MUST add descriptive PHPDoc comments above every test method.\n"
            . "- Class MUST be named exactly '{$testClass}' and extend 'PHPUnit\\Framework\\TestCase'.\n\n"
            . "### OUTPUT FORMAT:\n"
            . "Output ONLY the complete PHP test file inside a single markdown code block.\n"
            . "Do NOT return JSON. Do NOT explain outside the code block.\n\n"
            . "```php\n"
            . "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$testNs};\n\n"
            . "// ...\n"
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
