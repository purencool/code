<?php

declare(strict_types=1);

namespace App\Services\Scanner\Parsers;

class PhpParser implements LanguageParserInterface
{
    public function name(): string
    {
        return 'php';
    }

    public function supports(string $filePath, string $extension): bool
    {
        return strtolower($extension) === 'php' && !str_ends_with($filePath, '.blade.php');
    }

    public function parse(string $filePath, string $content): array
    {
        $defines = [];
        $imports = [];
        $extends = [];

        $tokens = token_get_all($content);
        $count = count($tokens);

        $namespace = '';
        $inClassBody = false;
        $braceDepth = 0;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token === '{') {
                $braceDepth++;
                continue;
            }

            if ($token === '}') {
                $braceDepth--;
                if ($braceDepth <= 0) {
                    $inClassBody = false;
                    $braceDepth = 0;
                }
                continue;
            }

            if (!is_array($token)) {
                continue;
            }

            $id = $token[0];
            $text = $token[1];

            // 1. Capture Namespace
            if ($id === T_NAMESPACE) {
                $ns = '';
                $i++;
                while ($i < $count && $tokens[$i] !== ';' && $tokens[$i] !== '{') {
                    if (is_array($tokens[$i])) {
                        $ns .= $tokens[$i][1];
                    }
                    $i++;
                }
                $namespace = trim($ns);
                continue;
            }

            // 2. Capture Top-Level "use" Imports (Ignore traits inside classes & closures)
            if ($id === T_USE && !$inClassBody) {
                // Peek ahead: If followed by '(', it's a closure function() use ($var), not an import
                $next = $this->nextNonWhitespaceToken($tokens, $i);
                if ($next !== null && $next[0] === '(') {
                    continue;
                }

                $import = '';
                $i++;
                while ($i < $count && $tokens[$i] !== ';') {
                    if (is_array($tokens[$i])) {
                        // Stop if aliasing with 'as'
                        if ($tokens[$i][0] === T_AS) {
                            while ($i < $count && $tokens[$i] !== ';') {
                                $i++;
                            }
                            break;
                        }
                        // Ignore 'function' or 'const' modifiers in use statements
                        if ($tokens[$i][0] !== T_FUNCTION && $tokens[$i][0] !== T_CONST) {
                            $import .= $tokens[$i][1];
                        }
                    }
                    $i++;
                }

                $cleanImport = trim($import);
                if ($cleanImport !== '' && !str_contains($cleanImport, '{')) {
                    $imports[] = $cleanImport;
                }
                continue;
            }

            // 3. Capture Class / Interface / Trait / Enum definitions
            $isDefinition = in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT], true)
                || (defined('T_ENUM') && $id === T_ENUM);

            if ($isDefinition && !$inClassBody) {
                // Ensure this isn't ::class constant resolution
                $prev = $this->prevNonWhitespaceToken($tokens, $i);
                if ($prev !== null && is_array($prev) && $prev[0] === T_PAAMAYIM_NEKUDOTAYIM) {
                    continue;
                }

                $nameToken = $this->nextNonWhitespaceToken($tokens, $i);
                if ($nameToken !== null && is_array($nameToken) && $nameToken[0] === T_STRING) {
                    $className = $nameToken[1];
                    $defines[] = $namespace !== '' ? "{$namespace}\\{$className}" : $className;
                    $inClassBody = true;
                }
                continue;
            }

            // 4. Capture Extends / Implements
            if (($id === T_EXTENDS || $id === T_IMPLEMENTS) && $inClassBody) {
                $i++;
                while ($i < $count && $tokens[$i] !== '{') {
                    if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                        $extends[] = trim($tokens[$i][1]);
                    }
                    $i++;
                }
                if ($i < $count && $tokens[$i] === '{') {
                    $braceDepth++;
                }
            }
        }

        return [
            'defines' => array_values(array_unique($defines)),
            'imports' => array_values(array_unique($imports)),
            'extends' => array_values(array_unique($extends)),
        ];
    }

    private function nextNonWhitespaceToken(array $tokens, int $currentIndex): ?array
    {
        $count = count($tokens);
        for ($j = $currentIndex + 1; $j < $count; $j++) {
            $t = $tokens[$j];
            if (is_array($t)) {
                if ($t[0] !== T_WHITESPACE && $t[0] !== T_COMMENT && $t[0] !== T_DOC_COMMENT) {
                    return $t;
                }
            } elseif (is_string($t) && trim($t) !== '') {
                return [$t, $t];
            }
        }
        return null;
    }

    private function prevNonWhitespaceToken(array $tokens, int $currentIndex): ?array
    {
        for ($j = $currentIndex - 1; $j >= 0; $j--) {
            $t = $tokens[$j];
            if (is_array($t)) {
                if ($t[0] !== T_WHITESPACE && $t[0] !== T_COMMENT && $t[0] !== T_DOC_COMMENT) {
                    return $t;
                }
            } elseif (is_string($t) && trim($t) !== '') {
                return [$t, $t];
            }
        }
        return null;
    }
}
