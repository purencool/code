<?php

declare(strict_types=1);

namespace App\Services\Scanner\Parsers;

class DartParser implements LanguageParserInterface
{
    public function name(): string
    {
        return 'dart';
    }

    public function supports(string $filePath, string $extension): bool
    {
        return strtolower($extension) === 'dart';
    }

    public function parse(string $filePath, string $content): array
    {
        $defines = [];
        $imports = [];
        $extends = [];

        if (preg_match_all('/import\s+[\'"](.*?)[\'"];/', $content, $im)) {
            foreach ($im[1] as $imp) {
                $imports[] = trim($imp);
            }
        }

        if (preg_match_all('/(?:abstract\s+)?class\s+([A-Za-z0-9_]+)(?:<[^>{]+>)?(?:\s+([^{;]+))?\{/s', $content, $cm, PREG_SET_ORDER)) {
            foreach ($cm as $match) {
                $defines[] = trim($match[1]);

                $clause = trim($match[2] ?? '');
                if ($clause !== '') {
                    $sections = preg_split('/\b(?:extends|with|implements)\b/', $clause);
                    if (is_array($sections)) {
                        foreach ($sections as $section) {
                            $items = explode(',', $section);
                            foreach ($items as $item) {
                                $clean = trim($item);
                                if ($clean !== '') {
                                    $extends[] = $clean;
                                }
                            }
                        }
                    }
                }
            }
        }

        return [
            'defines' => array_values(array_unique($defines)),
            'imports' => array_values(array_unique($imports)),
            'extends' => array_values(array_unique($extends)),
        ];
    }
}
