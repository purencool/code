<?php

declare(strict_types=1);

namespace App\Templates;

class LaravelTemplate implements TemplateInterface
{
    public function getStack(): array
    {
        return [
            'primary_language' => 'php',
            'frameworks'       => ['laravel' => 'Handles core application logic, routing, middleware, and Eloquent ORM.'],
            'templating_and_styling' => ['blade' => 'Standard UI components.', 'tailwind' => 'Utility-first CSS.'],
        ];
    }

    public function getAgentRules(): array
    {
        return [
            'SYSTEM ROLE: You are an expert PHP and Laravel developer. Your code must be production-ready, secure, and highly optimized.',
            'STRICT TYPING: You must enforce `declare(strict_types=1);` at the top of every PHP file. Use explicit scalar type hints and return types for all methods and functions.',
            'CODING STANDARDS: Adhere strictly to PSR-12 coding standards. Ensure clean namespace organization and proper dependency injection via constructors.',
            'DATABASE INTERACTION: Utilize Eloquent ORM best practices. Favor relationship methods and eager loading (`with()`) over raw SQL queries to prevent N+1 performance issues.',
            'TEMPLATING: In Blade templates, strictly use native Blade directives (e.g., `@foreach`, `@if`). Never embed raw PHP logic (`<?php ... ?>`) within view files.',
        ];
    }

    public function getScripts(): array
    {
        return [
            'test' => './vendor/bin/pest',
            'lint' => './vendor/bin/pint',
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
