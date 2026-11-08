<?php

declare(strict_types=1);

namespace App\Services\Agent;

use Illuminate\Support\Facades\File;
use SplFileInfo;

class CodebaseExplorer
{
    /**
     * Search directory files for a symbol while excluding blacklisted directory patterns.
     *
     * @param array<int, string> $excludePatterns
     * @return array<int, array{file: string, line: int, text: string}>
     */
    public function grep(string $basePath, string $symbol, array $excludePatterns = ['vendor', 'node_modules', '.git']): array
    {
        if (!File::isDirectory($basePath)) {
            return [];
        }

        $allFiles = File::allFiles($basePath);
        $matches = [];

        /** @var SplFileInfo $file */
        foreach ($allFiles as $file) {
            $normalizedPath = str_replace('\\', '/', $file->getPathname());

            foreach ($excludePatterns as $pattern) {
                $trimmed = trim($pattern, '/');
                if ($trimmed !== '' && str_contains($normalizedPath, '/' . $trimmed . '/')) {
                    continue 2;
                }
            }

            $lines = file($normalizedPath, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if (str_contains($line, $symbol)) {
                    $matches[] = [
                        'file' => $normalizedPath,
                        'line' => $index + 1,
                        'text' => trim($line),
                    ];
                }
            }
        }

        return $matches;
    }

    /**
     * Extract public and protected method signatures, omitting method bodies and private methods.
     *
     * @return array<int, string>
     */
    public function extractMethodSignatures(string $filePath): array
    {
        $outline = $this->parseFile($filePath);
        return $outline['signatures'];
    }

    /**
     * Extract symbol outline with visibility, docblocks, and return types for public/protected methods.
     *
     * @return array{classes: array<string, array{methods: array<string, array{visibility: string, docblock: string, return_type: string}>}>}
     */
    public function extractOutline(string $filePath): array
    {
        $parsed = $this->parseFile($filePath);
        return ['classes' => $parsed['classes']];
    }

    /**
     * Parse PHP file using native tokenizer into class outline and clean signatures.
     *
     * @return array{
     *     signatures: array<int, string>,
     *     classes: array<string, array{methods: array<string, array{visibility: string, docblock: string, return_type: string}>}>
     * }
     */
    protected function parseFile(string $filePath): array
    {
        if (!File::exists($filePath)) {
            return ['signatures' => [], 'classes' => []];
        }

        $content = (string) File::get($filePath);
        $tokens = token_get_all($content);
        $count = count($tokens);

        $currentClass = null;
        $classes = [];
        $signatures = [];

        $lastDocComment = '';
        $currentVisibility = null;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                if ($token[0] === T_DOC_COMMENT) {
                    $lastDocComment = $token[1];
                    continue;
                }

                if ($token[0] === T_CLASS) {
                    $j = $i + 1;
                    while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                        $j++;
                    }
                    if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $currentClass = $tokens[$j][1];
                        if (!isset($classes[$currentClass])) {
                            $classes[$currentClass] = ['methods' => []];
                        }
                    }
                    $i = $j;
                    continue;
                }

                if (in_array($token[0], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                    $currentVisibility = $token[1];
                    continue;
                }

                if ($token[0] === T_FUNCTION) {
                    $visibility = $currentVisibility ?? 'public';
                    $docblock = $lastDocComment;
                    $lastDocComment = '';
                    $currentVisibility = null;

                    $j = $i + 1;
                    while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                        $j++;
                    }

                    if ($j >= $count || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) {
                        continue;
                    }

                    $methodName = $tokens[$j][1];
                    $j++;

                    $params = '';
                    $returnType = '';
                    $inReturnType = false;

                    while ($j < $count) {
                        $tok = $tokens[$j];
                        if ($tok === '{' || $tok === ';') {
                            break;
                        }

                        $tokVal = is_array($tok) ? $tok[1] : $tok;

                        if ($tok === ':') {
                            $inReturnType = true;
                            $j++;
                            continue;
                        }

                        if ($inReturnType) {
                            $returnType .= $tokVal;
                        } else {
                            $params .= $tokVal;
                        }
                        $j++;
                    }

                    $returnType = trim($returnType);
                    $trimmedParams = trim($params);

                    $signature = "{$visibility} function {$methodName}" . $trimmedParams;
                    if ($returnType !== '') {
                        $signature .= ": {$returnType}";
                    }

                    if ($visibility !== 'private') {
                        $signatures[] = $signature;

                        if ($currentClass !== null) {
                            $classes[$currentClass]['methods'][$methodName] = [
                                'visibility'  => $visibility,
                                'docblock'    => $docblock,
                                'return_type' => $returnType,
                            ];
                        }
                    }

                    $i = $j - 1;
                    continue;
                }
            } else {
                if ($token === ';' || $token === '}') {
                    $lastDocComment = '';
                    $currentVisibility = null;
                }
            }
        }

        return [
            'signatures' => $signatures,
            'classes'    => $classes,
        ];
    }
}
