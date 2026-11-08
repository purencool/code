<?php

declare(strict_types=1);

namespace App\Templates;

interface TemplateInterface
{
    /**
     * Get the stack specifications and technology metadata.
     *
     * @return array<string, mixed>
     */
    public function getStack(): array;

    /**
     * Get the list of enforced agent rules and system directives.
     *
     * @return array<int, string>
     */
    public function getAgentRules(): array;

    /**
     * Define the executable scripts mapped to simple keys (like test, lint, build).
     *
     * @return array<string, string>
     */
    public function getScripts(): array;

    /**
     * Define workspace context scanning boundaries and rules.
     *
     * @return array{
     *     include_dirs: array<int, string>,
     *     file_extensions: array<int, string>,
     *     exclude_patterns: array<int, string>,
     *     max_file_size_kb: int,
     *     max_context_tokens: int
     * }
     */
    public function getContextScanning(): array;

    /**
     * Get the list of enforced agent schema directives.
     *
     * @return array<int, string>
     */
    public function getSchemas(): array;
}
