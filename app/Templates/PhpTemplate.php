<?php

declare(strict_types=1);

namespace App\Templates;

class PhpTemplate implements TemplateInterface
{
    public function getStack(): array
    {
        return [
            'primary_language' => 'php',
            'frameworks'       => [],
            'templating_and_styling' => [],
        ];
    }

    public function getAgentRules(): array
    {
        return [
            "SYSTEM ROLE: You are an expert modern PHP engineer. Your code must be production-ready, clean, and strictly typed.",
            "STRICT TYPING: Enforce declare(strict_types=1); on line 2 of every PHP file. Use explicit property, parameter, and return types across all methods.",
            "CODING STANDARDS & CLEANLINESS: Adhere strictly to PSR-12 coding style and PSR-4 autoloading standards. NEVER include a closing '?>' tag in any PHP file. Never output git diff markers ('+', '-') or conversational text inside code blocks.",
            "FORMATTING & SPACING: Always insert exactly one empty line between class methods (between the closing brace '}' of one method and the starting PHPDoc '/**' of the next method).",
            "NAMESPACES & AUTOLOADING: Implementation classes under 'app/Services/...' MUST declare 'namespace App\\Services;'. Test classes under 'tests/Unit/...' MUST declare 'namespace Tests\\Unit;' and explicitly import the target class with 'use App\\Services\\<ClassName>;'. Test classes under 'tests/Feature/...' MUST declare 'namespace Tests\\Feature;'.",
            "DOCUMENTATION (PHPDoc): Every class must have a class-level PHPDoc docblock explaining its responsibility. Every method must have a full PHPDoc docblock detailing description, @param, @return, and any @throws annotations.",
            "DEPENDENCY INJECTION & ARCHITECTURE: Favor constructor dependency injection over static singletons or global state. Keep classes focused, following SOLID principles.",
            "MODERN FEATURES: Leverage modern PHP 8.2+ features (enums, readonly properties/classes, match expressions, constructor promotion) where appropriate."
        ];
    }

    /**
     * @return array{
     *     test_generation: array{explanation: string, test_files: array<int, array{path: string, code: string}>},
     *     implementation: array{files: array<int, array{path: string, content: string}>}
     * }
     */
    public function getSchemas(): array
    {
        return [
            'test_generation' => [
                'explanation' => 'Brief testing strategy',
                'test_files' => [
                    [
                        'path' => 'tests/Unit/CalculatorTest.php',
                        'code' => "<?php\n\ndeclare(strict_types=1);\n\nnamespace Tests\\Unit;\n\nuse App\\Services\\Calculator;\nuse PHPUnit\\Framework\\TestCase;\n\n/**\n * Unit tests for Calculator operations.\n */\nclass CalculatorTest extends TestCase\n{\n    // ...\n}",
                    ],
                ],
            ],
            'implementation' => [
                'files' => [
                    [
                        'path' => 'app/Services/Calculator.php',
                        'content' => "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Services;\n\n/**\n * Calculator Service\n */\nclass Calculator\n{\n    // ...\n}",
                    ],
                ],
            ],
        ];
    }

    public function getScripts(): array
    {
        return [
            'test' => './vendor/bin/phpunit',
            'lint' => './vendor/bin/phpcs',
        ];
    }

    /**
     * @return array{
     *     include_dirs: array<int, string>,
     *     file_extensions: array<int, string>,
     *     exclude_patterns: array<int, string>,
     *     max_file_size_kb: int,
     *     max_context_tokens: int
     * }
     */
    public function getContextScanning(): array
    {
        return [
            'include_dirs' => ['src', 'app', 'tests'],
            'file_extensions' => ['php'],
            'exclude_patterns' => ['node_modules', 'vendor', '.git', 'build', 'dist', '.dart_tool', 'cache'],
            'max_file_size_kb' => 300,
            'max_context_tokens' => 1200,
        ];
    }
}
