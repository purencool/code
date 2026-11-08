<?php

declare(strict_types=1);

namespace App\Templates;

class PncAgentTemplate implements TemplateInterface
{
    public function getStack(): array
    {
        return [
            'primary_language' => 'php',
            'frameworks'       => [
                'laravel-zero' => 'Micro-framework for console applications.',
                'neuron-ai'    => 'AI agent framework.',
            ],
            'templating_and_styling' => [],
        ];
    }

    public function getAgentRules(): array
    {
        return [
            'SYSTEM ROLE: You are an expert PHP systems engineer specializing in Command Line Interfaces (CLI), Laravel Zero, and Neuron AI. Your code must be lightweight, fast, and strictly typed.',
            'STRICT TYPING: Enforce `declare(strict_types=1);` on line 2 of every PHP file. Use explicit property types, parameter types, and return types across all methods.',
            'SOLID ARCHITECTURE: Commands (`app/Commands/`) must act solely as orchestrators handling I/O. All business logic, file system operations, and API calls must be abstracted into dedicated, testable Service classes (`app/Services/`).',
            'CLI I/O CONVENTIONS: Use native console methods (`$this->info()`, `$this->error()`, `$this->ask()`, etc.) for user interactions. Never use `echo`, `print`, or `die()` in application logic.',
            'AI TOOLING: When building custom tools for the Neuron AI engine, ensure they properly implement the required Tool interfaces, maintain strict parameter typing, and return context-efficient strings or JSON.',
        ];
    }

    public function getScripts(): array
    {
        return [
            'test'  => './vendor/bin/pest',
            'build' => 'php pncagent app:build pncagent',
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
            'include_dirs' => ['app', 'src', 'lib', 'resources', 'tests'],
            'file_extensions' => ['php', 'js', 'ts', 'dart', 'blade.php', 'twig', 'scss'],
            'exclude_patterns' => ['node_modules', 'vendor', '.git', 'build', 'dist', '.dart_tool'],
            'max_file_size_kb' => 300,
            'max_context_tokens' => 1200,
        ];
    }

    public function getSchemas(): array
    {
        return [];
    }
}
