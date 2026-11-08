<?php

declare(strict_types=1);

namespace App\Services\Scanner\Parsers;

class JavaScriptParser implements LanguageParserInterface
{
    private const SUPPORTED_EXTENSIONS = ['js', 'jsx', 'ts', 'tsx', 'mjs', 'cjs'];

    public function name(): string
    {
        return 'javascript';
    }

    public function supports(string $filePath, string $extension): bool
    {
        return in_array(strtolower($extension), self::SUPPORTED_EXTENSIONS, true);
    }

    public function parse(string $filePath, string $content): array
    {
        $defines = [];
        $imports = [];

        if (preg_match_all('/(?:import\s+.*?from\s+[\'"](.*?)[\'"]|require\([\'"](.*?)[\'"]\))/', $content, $im)) {
            $matches = array_filter(array_merge($im[1], $im[2]));
            foreach ($matches as $imp) {
                $imports[] = trim($imp);
            }
        }

        if (preg_match_all('/export\s+(?:default\s+)?(?:class|function|const|let|var)\s+([A-Za-z0-9_]+)/', $content, $dm)) {
            foreach ($dm[1] as $def) {
                $defines[] = trim($def);
            }
        }

        return [
            'defines' => array_values(array_unique($defines)),
            'imports' => array_values(array_unique($imports)),
            'extends' => [],
        ];
    }
}
