<?php

declare(strict_types=1);

namespace App\Services\Scanner\Parsers;

class StyleParser implements LanguageParserInterface
{
    private const SUPPORTED_EXTENSIONS = ['scss', 'sass', 'css'];

    public function name(): string
    {
        return 'style';
    }

    public function supports(string $filePath, string $extension): bool
    {
        return in_array(strtolower($extension), self::SUPPORTED_EXTENSIONS, true);
    }

    public function parse(string $filePath, string $content): array
    {
        $imports = [];

        if (preg_match_all('/@(use|forward|import)\s+[\'"](.*?)[\'"]/', $content, $sm)) {
            foreach ($sm[2] as $inc) {
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
