<?php

declare(strict_types=1);

namespace App\Services\Scanner\Parsers;

interface LanguageParserInterface
{
    /**
     * Unique identifier for this parser (e.g., 'php', 'javascript', 'dart', 'template', 'style').
     */
    public function name(): string;

    /**
     * Determine whether this parser supports the given file and extension.
     */
    public function supports(string $filePath, string $extension): bool;

    /**
     * Parse structural wiring (definitions, imports, extensions) from file content.
     *
     * @return array{
     *     defines: array<int, string>,
     *     imports: array<int, string>,
     *     extends: array<int, string>
     * }
     */
    public function parse(string $filePath, string $content): array;
}
