<?php

declare(strict_types=1);

namespace App\Services\Scanner\Parsers;

class TemplateParser implements LanguageParserInterface
{
    public function name(): string
    {
        return 'template';
    }

    public function supports(string $filePath, string $extension): bool
    {
        return str_ends_with($filePath, '.blade.php') || strtolower($extension) === 'twig';
    }

    public function parse(string $filePath, string $content): array
    {
        $imports = [];

        if (preg_match_all('/@(extends|include|component)\([\'"](.*?)[\'"]\)/', $content, $bm)) {
            foreach ($bm[2] as $inc) {
                $imports[] = trim($inc);
            }
        }

        if (preg_match_all('/\{%\s*(?:extends|include)\s+[\'"](.*?)[\'"]\s*%}/', $content, $tm)) {
            foreach ($tm[1] as $inc) {
                $imports[] = trim($inc);
            }
        }

        return [
            'defines' => [],
            'imports' => array_values(array_unique($imports)),
            'extends' => [],
        ];
    }
}
