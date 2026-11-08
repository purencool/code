<?php

declare(strict_types=1);

namespace App\Templates;

class NodeJsTemplate implements TemplateInterface
{
    public function getStack(): array
    {
        return [
            'primary_language' => 'javascript',
            'frameworks'       => ['express' => 'Handles REST API endpoints.'],
            'templating_and_styling' => [],
        ];
    }

    public function getAgentRules(): array
    {
        return [
            'SYSTEM ROLE: You are an expert JavaScript and Node.js backend developer. Your code must be non-blocking, scalable, and secure.',
            'MODERN SYNTAX: Enforce modern ES6+ syntax. Heavily utilize ES modules (`import`/`export`) and destructuring assignments.',
            'ASYNCHRONOUS LOGIC: Prioritize `async/await` syntax over raw Promises or callback chains to maintain readable execution flow.',
            'ERROR HANDLING: Implement robust, centralized error handling. All asynchronous route handlers and middleware must catch and forward exceptions using a unified error response format.',
            'STATE & MUTATION: Ensure strict null checks. Treat variables as immutable where possible (`const` over `let`) and avoid mutating external state unexpectedly.',
        ];
    }

    public function getScripts(): array
    {
        return [
            'test' => 'npm run test',
            'start' => 'node index.js',
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
