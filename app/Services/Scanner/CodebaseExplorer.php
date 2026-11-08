<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use Illuminate\Support\Facades\File;
use SplFileInfo;

class CodebaseExplorer
{
    /**
     * Scan directory for source files while excluding blacklisted directory patterns.
     *
     * @param array<int, string> $excludePatterns
     * @return array<int, string>
     */
    public function getSourceFiles(string $basePath, array $excludePatterns = ['vendor', 'node_modules']): array
    {
        if (!File::isDirectory($basePath)) {
            return [];
        }

        $allFiles = File::allFiles($basePath);
        $filtered = [];

        /** @var SplFileInfo $file */
        foreach ($allFiles as $file) {
            $normalizedPath = str_replace('\\', '/', $file->getPathname());

            foreach ($excludePatterns as $pattern) {
                if (str_contains($normalizedPath, '/' . trim($pattern, '/') . '/')) {
                    continue 2;
                }
            }

            $filtered[] = $normalizedPath;
        }

        return $filtered;
    }

    /**
     * Extract public and protected method signatures, omitting method bodies and private methods.
     *
     * @return array<int, string>
     */
    public function extractPublicAndProtectedSignatures(string $filePath): array
    {
        if (!File::exists($filePath)) {
            return [];
        }

        $content = (string) File::get($filePath);
        $tokens = token_get_all($content);
        $count = count($tokens);
        $signatures = [];

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }

            // Look backward for explicit visibility modifier (defaults to public if absent)
            $visibility = 'public';
            for ($k = $i - 1; $k >= 0; $k--) {
                if (!is_array($tokens[$k])) {
                    break;
                }
                if (in_array($tokens[$k][0], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                    $visibility = $tokens[$k][1];
                    break;
                }
                if ($tokens[$k][0] === T_WHITESPACE || $tokens[$k][0] === T_COMMENT || $tokens[$k][0] === T_DOC_COMMENT) {
                    continue;
                }
                break;
            }

            // Skip private methods
            if ($visibility === 'private') {
                continue;
            }

            // Find method name
            $j = $i + 1;
            while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            $methodName = is_array($tokens[$j]) ? $tokens[$j][1] : '';

            // Advance past method name to parameter list and return type
            $j++;
            $signatureParams = '';
            while ($j < $count && $tokens[$j] !== '{' && $tokens[$j] !== ';') {
                $signatureParams .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                $j++;
            }

            $signatures[] = trim("{$visibility} function {$methodName}" . trim($signatureParams));
        }

        return $signatures;
    }

    /**
     * Search file line-by-line for a given symbol, returning line numbers and trimmed text.
     *
     * @return array<int, array{line: int, text: string}>
     */
    public function grepSymbol(string $filePath, string $symbol): array
    {
        if (!File::exists($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $matches = [];
        foreach ($lines as $index => $line) {
            if (str_contains($line, $symbol)) {
                $matches[] = [
                    'line' => $index + 1,
                    'text' => trim($line),
                ];
            }
        }

        return $matches;
    }
}
