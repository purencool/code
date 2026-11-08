<?php

declare(strict_types=1);

namespace App\Services\Tdd;

class ImplementationPromptBuilder
{
    public function __construct(
        private readonly TaskMetadataParser $metadataParser = new TaskMetadataParser(),
        private readonly PathNamespaceResolver $pathResolver = new PathNamespaceResolver(),
        private readonly DependencyInstructionBuilder $dependencyBuilder = new DependencyInstructionBuilder(),
    ) {
    }

    /**
     * Step 3 (TDD): Implement production class matching approved test plan.
     *
     * @param array{explanation?: string, test_files: array<int, array{path: string, code: string}>} $testPlan
     * @param array<string, mixed> $config
     */
    public function build(string $task, array $testPlan, array $config): string
    {
        $meta = $this->metadataParser->parse($task);
        $rules = $this->formatRules($config);

        $implPath = $meta['target_class'];
        $implNs = $this->pathResolver->resolveNamespaceFromPath($implPath, 'App\\Services');
        $implClass = $this->pathResolver->resolveClassNameFromPath($implPath, 'GeneratedClass');

        $testSuiteSummary = '';
        foreach ($testPlan['test_files'] as $t) {
            $testSuiteSummary .= "// File: " . $t['path'] . "\n" . $t['code'] . "\n\n";
        }

        $depInstructions = $this->dependencyBuilder->buildImplementationInjectionInstructions($meta['dependencies']);

        return "SYSTEM INSTRUCTION:\n"
            . "You are an automated code generator executing Step 3 (Production Implementation) of TDD.\n"
            . "Your job is to write the PRODUCTION APPLICATION CLASS that makes the approved unit tests pass.\n\n"
            . "### REFERENCE TEST SUITE (DO NOT COPY OR REGENERATE THIS):\n"
            . "The tests below define what your production class must satisfy:\n"
            . "```php\n"
            . $testSuiteSummary
            . "```\n\n"
            . "### USER REQUIREMENTS:\n"
            . $meta['task'] . "\n\n"
            . $depInstructions
            . "### STRICT PRODUCTION ARCHITECTURE (CRITICAL):\n"
            . "- Target File: {$implPath}\n"
            . "- Namespace: {$implNs}\n"
            . "- Class Name: {$implClass}\n\n"
            . "### FORBIDDEN CONSTRUCTS (INSTANT FAILURE):\n"
            . "- NEVER write a test file or test methods ('testCancel...', 'testSum...', etc.).\n"
            . "- NEVER extend 'TestCase' or use 'PHPUnit\\Framework\\TestCase'.\n"
            . "- NEVER use 'Tests\\...' namespace. You are in '{$implNs}'.\n"
            . "- Class name MUST be '{$implClass}', NOT '{$implClass}Test'.\n\n"
            . "### ARCHITECTURAL RULES:\n"
            . $rules . "\n\n"
            . "### OUTPUT FORMAT:\n"
            . "Output ONLY the complete production PHP class inside a single markdown code block.\n"
            . "Do NOT return JSON. Do NOT output tests.\n\n"
            . "Example format:\n"
            . "```php\n"
            . "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace {$implNs};\n\n"
            . "/**\n"
            . " * Class {$implClass}\n"
            . " */\n"
            . "class {$implClass}\n"
            . "{\n"
            . "    // Business logic satisfying tests...\n"
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
