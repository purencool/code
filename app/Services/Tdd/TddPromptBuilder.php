<?php

declare(strict_types=1);

namespace App\Services\Tdd;

use RuntimeException;

class TddPromptBuilder
{
    /**
     * Step 1 (TDD): Generate test from user requirements.
     *
     * @param array<string, mixed> $config
     */
    public function buildTestGenerationPrompt(string $task, string $wiringContext, array $config): string
    {
        $meta = $this->parseTaskMetadata($task);
        $rules = implode("\n", array_map(fn (string $r): string => "- {$r}", $config['agent_rules'] ?? []));

        $testPath = $meta['target_test'];
        $testNs = $this->resolveNamespaceFromPath($testPath, 'Tests\\Unit');
        $testClass = $this->resolveClassNameFromPath($testPath, 'GeneratedTest');

        $implPath = $meta['target_class'];
        $implNs = $this->resolveNamespaceFromPath($implPath, 'App\\Services');
        $implClass = $this->resolveClassNameFromPath($implPath, 'GeneratedClass');
        $implFqcn = $implNs . '\\' . $implClass;

        $depInstructions = '';
        if (!empty($meta['dependencies'])) {
            $depInstructions = "### MANDATORY CONSTRUCTOR DEPENDENCIES:\n"
                . "The target class requires these dependencies injected via constructor:\n";
            foreach ($meta['dependencies'] as $dep) {
                $depInstructions .= "- {$dep}\n";
            }
            $depInstructions .= "\nTEST SETUP REQUIREMENTS:\n"
                . "- Import each dependency interface/class with 'use <Namespace>\\<Class>;'.\n"
                . "- In setUp() or test methods, mock each dependency: '\$this->createMock(<DependencyClass>::class)'.\n"
                . "- Pass mocks into 'new {$implClass}(...)' in the constructor.\n"
                . "- Configure expectations and return values on the mocks to test business logic.\n\n";
        }

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
     * Step 1 (CTT): Generate tests for an existing production class file.
     *
     * @param array<string, mixed> $config
     */
    public function buildCttTestPrompt(string $task, string $classContent, array $config): string
    {
        $meta = $this->parseTaskMetadata($task);
        $rules = implode("\n", array_map(fn (string $r): string => "- {$r}", $config['agent_rules'] ?? []));

        $testPath = $meta['target_test'];
        $testNs = $this->resolveNamespaceFromPath($testPath, 'Tests\\Unit');
        $testClass = $this->resolveClassNameFromPath($testPath, 'GeneratedTest');

        $implPath = $meta['target_class'];
        $implNs = $this->resolveNamespaceFromPath($implPath, 'App\\Services');
        $implClass = $this->resolveClassNameFromPath($implPath, 'GeneratedClass');
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
     * Step 3 (TDD): Implement production class matching approved test plan.
     * Fences the test suite at the top and anchors production constraints at the bottom.
     *
     * @param array{explanation?: string, test_files: array<int, array{path: string, code: string}>} $testPlan
     * @param array<string, mixed> $config
     */
    public function buildImplementationPrompt(string $task, array $testPlan, array $config): string
    {
        $meta = $this->parseTaskMetadata($task);
        $rules = implode("\n", array_map(fn (string $r): string => "- {$r}", $config['agent_rules'] ?? []));

        $implPath = $meta['target_class'];
        $implNs = $this->resolveNamespaceFromPath($implPath, 'App\\Services');
        $implClass = $this->resolveClassNameFromPath($implPath, 'GeneratedClass');

        $testSuiteSummary = '';
        foreach ($testPlan['test_files'] as $t) {
            $testSuiteSummary .= "// File: " . $t['path'] . "\n" . $t['code'] . "\n\n";
        }

        $depInstructions = '';
        if (!empty($meta['dependencies'])) {
            $depInstructions = "### CONSTRUCTOR DEPENDENCY INJECTION:\n"
                . "The production class MUST inject and store these dependencies using constructor promotion:\n";
            foreach ($meta['dependencies'] as $dep) {
                $depInstructions .= "- {$dep}\n";
            }
            $depInstructions .= "- Example: 'public function __construct(private readonly <Type> \$var, ...) {}'.\n\n";
        }

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
     * Pure array-driven line scanner. Supports 'Target Type', 'Target Test', 'Target Class', 'Dependencies', 'Task'.
     * Falls back to natural-language class inference for unstructured one-liner tasks.
     *
     * @return array{target_type: string, target_test: string, target_class: string, dependencies: array<int, string>, task: string}
     */
    public function parseTaskMetadata(string $rawTask): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $rawTask);
        $lines = explode("\n", $normalized);

        $targetType = 'TDD';
        $targetTest = null;
        $targetClass = null;
        $dependencies = [];
        $taskBodyLines = [];
        $inTaskBody = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                if ($inTaskBody) {
                    $taskBodyLines[] = '';
                }
                continue;
            }

            $clean = ltrim($trimmed, "-* \t");
            $clean = str_ireplace(['**', '__', '`'], '', $clean);

            if (str_contains($clean, ':')) {
                [$keyPart, $valPart] = explode(':', $clean, 2);
                $key = strtolower(trim($keyPart));
                $val = trim($valPart);

                if ($key === 'target type' || $key === 'type') {
                    $upper = strtoupper($val);
                    if ($upper !== '') {
                        $targetType = $upper;
                    }
                    $inTaskBody = false;
                    continue;
                }

                if ($key === 'target test') {
                    $targetTest = $val;
                    $inTaskBody = false;
                    continue;
                }

                if ($key === 'target class') {
                    $targetClass = $val;
                    $inTaskBody = false;
                    continue;
                }

                if ($key === 'dependencies') {
                    $rawDeps = explode(',', $val);
                    $dependencies = array_values(array_filter(array_map('trim', $rawDeps)));
                    $inTaskBody = false;
                    continue;
                }

                if ($key === 'task') {
                    $inTaskBody = true;
                    if ($val !== '') {
                        $taskBodyLines[] = $val;
                    }
                    continue;
                }
            }

            if ($inTaskBody) {
                $taskBodyLines[] = $line;
            } else {
                $taskBodyLines[] = $trimmed;
            }
        }

        // 1. Auto-infer missing counterpart if one was explicitly specified
        if ($targetTest === null && $targetClass !== null) {
            $cleanClass = str_starts_with($targetClass, 'app/') ? substr($targetClass, 4) : $targetClass;
            $targetTest = 'tests/Unit/' . str_replace('.php', 'Test.php', $cleanClass);
        } elseif ($targetClass === null && $targetTest !== null) {
            $cleanTest = str_starts_with($targetTest, 'tests/Unit/') ? substr($targetTest, 11) : $targetTest;
            $targetClass = 'app/' . str_replace('Test.php', '.php', $cleanTest);
        }

        // 2. Intelligent inference for unstructured CLI arguments (e.g. 'Write a Calculator class with add method')
        if ($targetTest === null && $targetClass === null) {
            $combinedText = implode(' ', $taskBodyLines);
            $inferredClass = null;

            $patterns = [
                '/(?:write|create|implement|build)\s+(?:a|an)?\s*([A-Za-z0-9_]+)\s+class/i',
                '/\b([A-Za-z0-9_]+)\s+class\b/i',
                '/\bclass\s+([A-Za-z0-9_]+)\b/i',
                '/(?:write|create|implement|build)\s+(?:a|an)?\s*([A-Z][a-zA-Z0-9]+)/i',
                '/\b([A-Z][a-zA-Z0-9]+(?:Service|Processor|Calculator|Manager|Handler|Repository|Helper|Factory|Builder|Client))\b/i',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $combinedText, $m)) {
                    $candidate = trim($m[1]);
                    $lower = strtolower($candidate);
                    if (!in_array($lower, ['php', 'tdd', 'ctt', 'unit', 'test', 'the', 'this', 'that', 'with', 'and', 'for', 'class'], true)) {
                        $inferredClass = ucfirst($candidate);
                        break;
                    }
                }
            }

            $className = $inferredClass ?? 'Service';
            $targetClass = "app/Services/{$className}.php";
            $targetTest = "tests/Unit/{$className}Test.php";
        }

        return [
            'target_type'   => $targetType,
            'target_test'   => $targetTest,
            'target_class'  => $targetClass,
            'dependencies'  => $dependencies,
            'task'          => trim(implode("\n", $taskBodyLines)),
        ];
    }

    public function resolveNamespaceFromPath(string $path, string $default): string
    {
        $dir = dirname($path);
        if ($dir === '.' || $dir === '') {
            return $default;
        }

        if (str_starts_with($dir, 'app/')) {
            return 'App\\' . str_replace('/', '\\', substr($dir, 4));
        }
        if (str_starts_with($dir, 'app')) {
            return 'App';
        }
        if (str_starts_with($dir, 'tests/')) {
            $sub = substr($dir, 6);
            return 'Tests\\' . ucfirst(str_replace('/', '\\', $sub));
        }

        return $default;
    }

    public function resolveClassNameFromPath(string $path, string $default): string
    {
        $filename = basename($path);
        if (str_ends_with($filename, '.php')) {
            return substr($filename, 0, -4);
        }
        return $default;
    }
}
