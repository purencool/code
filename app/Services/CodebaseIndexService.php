<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;
use Mockery\MockInterface;

class CodebaseIndexService
{
    protected AgentContextService $context;
    protected AgentPathService $paths;
    protected array $graph = [];

    public function __construct(AgentContextService $context, ?AgentPathService $paths = null)
    {
        $this->context = $context;

        if ($paths !== null) {
            $this->paths = $paths;
        } elseif ($context instanceof MockInterface) {
            try {
                $this->paths = $context->paths();
            } catch (\Throwable $e) {
                $workspace = null;
                try {
                    $workspace = $context->getWorkspaceRoot();
                } catch (\Throwable $t) {}

                $this->paths = function_exists('app') && app()->bound(AgentPathService::class)
                    ? app(AgentPathService::class)
                    : new AgentPathService($workspace);

                if ($workspace) {
                    $this->paths->setWorkspaceRoot($workspace);
                }
            }
        } else {
            $this->paths = $context->paths();
        }

        $this->loadCachedIndex();
    }

    public function paths(): AgentPathService
    {
        return $this->paths;
    }

    /**
     * Scan workspace directory across polyglot files and write to template-scoped cache.
     *
     * @return array<string, mixed>
     */
    public function scan(string $workspace): array
    {
        $cacheFile = $this->paths->getCacheDir('wiring.json');
        $cacheDir = dirname($cacheFile);

        if (!File::isDirectory($cacheDir)) {
            File::makeDirectory($cacheDir, 0755, true);
        }

        $symbols = [];
        $files = File::isDirectory($workspace) ? File::allFiles($workspace) : [];
        $agentDir = $this->paths->getDirectoryName();

        $supportedExtensions = ['php', 'js', 'ts', 'jsx', 'tsx', 'mjs', 'py'];

        foreach ($files as $file) {
            $rel = str_replace('\\', '/', $file->getRelativePathname());

            if (
                str_starts_with($rel, $agentDir . '/') ||
                str_starts_with($rel, 'vendor/') ||
                str_starts_with($rel, 'node_modules/') ||
                str_starts_with($rel, '.git/') ||
                str_starts_with($rel, 'storage/')
            ) {
                continue;
            }

            if (in_array(strtolower($file->getExtension()), $supportedExtensions, true)) {
                $inspection = $this->inspectFile($rel);
                foreach ($inspection['classes'] as $cls) {
                    $symbols[$cls] = [
                        'file'         => $rel,
                        'dependencies' => $inspection['dependencies'],
                    ];
                }
            }
        }

        $this->graph = [
            'scanned_at' => date('c'),
            'workspace'  => $workspace,
            'symbols'    => $symbols,
        ];

        File::put($cacheFile, (string) json_encode($this->graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $this->graph;
    }

    /**
     * Inspect declarations and dependencies for an individual file (PHP, JS/TS, Python).
     *
     * @return array{file: string, exists: bool, classes: array<int, string>, dependencies: array<int, string>}
     */
    public function inspectFile(string $targetFile): array
    {
        $workspace = $this->paths->getWorkspaceRoot();
        $fullPath = str_starts_with($targetFile, '/') ? $targetFile : $workspace . '/' . $targetFile;

        if (!File::exists($fullPath)) {
            return [
                'file'         => $targetFile,
                'exists'       => false,
                'classes'      => [],
                'dependencies' => [],
            ];
        }

        $content = (string) File::get($fullPath);
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        $classes = [];
        $dependencies = [];

        if ($ext === 'php') {
            $tokens = token_get_all($content);
            $count = count($tokens);

            for ($i = 0; $i < $count; $i++) {
                $token = $tokens[$i];
                if (is_array($token)) {
                    if ($token[0] === T_CLASS || $token[0] === T_INTERFACE || $token[0] === T_TRAIT) {
                        $j = $i + 1;
                        while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                            $j++;
                        }
                        if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                            $classes[] = $tokens[$j][1];
                        }
                    } elseif ($token[0] === T_USE) {
                        $useStmt = '';
                        $j = $i + 1;
                        while ($j < $count) {
                            $tok = $tokens[$j];
                            if ($tok === ';' || $tok === '{' || $tok === '(') {
                                break;
                            }
                            $useStmt .= is_array($tok) ? $tok[1] : $tok;
                            $j++;
                        }
                        $trimmed = trim($useStmt);
                        if ($trimmed !== '') {
                            $dependencies[] = $trimmed;
                        }
                    }
                }
            }
        } elseif (in_array($ext, ['js', 'ts', 'jsx', 'tsx', 'mjs'], true)) {
            if (preg_match_all('/class\s+([A-Za-z0-9_$]+)/', $content, $classMatches)) {
                $classes = array_merge($classes, $classMatches[1]);
            }

            if (preg_match_all('/(?:import|from)\s+[\'"]([^\'"]+)[\'"]/', $content, $importMatches)) {
                $dependencies = array_merge($dependencies, $importMatches[1]);
            }

            if (preg_match_all('/require\([\'"]([^\'"]+)[\'"]\)/', $content, $requireMatches)) {
                $dependencies = array_merge($dependencies, $requireMatches[1]);
            }
        } elseif ($ext === 'py') {
            if (preg_match_all('/^\s*class\s+([A-Za-z0-9_]+)/m', $content, $classMatches)) {
                $classes = array_merge($classes, $classMatches[1]);
            }

            if (preg_match_all('/^\s*import\s+([A-Za-z0-9_]+)/m', $content, $importMatches)) {
                $dependencies = array_merge($dependencies, $importMatches[1]);
            }
            if (preg_match_all('/^\s*from\s+([A-Za-z0-9_\.]+)\s+import/m', $content, $fromMatches)) {
                $dependencies = array_merge($dependencies, $fromMatches[1]);
            }
        }

        return [
            'file'         => $targetFile,
            'exists'       => true,
            'classes'      => array_values(array_unique($classes)),
            'dependencies' => array_values(array_unique($dependencies)),
        ];
    }

    /**
     * Extract architectural neighborhood context around symbols mentioned in a query or task.
     *
     * @return string|array<string, mixed>
     */
    public function getNeighborhoodContext(string $task, int $depth = 2): string|array
    {
        if (empty($this->graph)) {
            $this->loadCachedIndex();
        }

        // Clean token splitting without character-class delimiter collisions
        $keywords = preg_split('/[^\w]+/', strtolower($task));
        $keywords = array_filter((array) $keywords, fn (string $k) => strlen($k) > 2);

        $matched = [];
        $symbols = $this->graph['symbols'] ?? [];

        foreach ($keywords as $kw) {
            foreach ($symbols as $name => $meta) {
                if (str_contains(strtolower((string) $name), $kw)) {
                    $matched[$name] = $meta;
                }
            }
        }

        $lines = [];
        if (empty($matched)) {
            if (!empty($symbols)) {
                $sample = array_slice($symbols, 0, 5, true);
                $lines[] = "Overview of workspace symbols:";
                foreach ($sample as $name => $meta) {
                    $file = is_array($meta) ? ($meta['file'] ?? 'unknown') : 'unknown';
                    $lines[] = "- {$name} ({$file})";
                }
            } else {
                $lines[] = "No existing architectural wiring found for task.";
            }
        } else {
            $lines[] = "Architectural neighborhood for task: {$task}";
            foreach ($matched as $name => $meta) {
                $file = is_array($meta) ? ($meta['file'] ?? 'unknown') : 'unknown';
                $deps = is_array($meta) && !empty($meta['dependencies'])
                    ? implode(', ', (array) $meta['dependencies'])
                    : 'none';
                $lines[] = "- Symbol: {$name} in {$file} [Dependencies: {$deps}]";
            }
        }

        $summary = implode("\n", $lines);

        try {
            if (class_exists(TddWorkflowService::class)) {
                $ref = new \ReflectionMethod(TddWorkflowService::class, 'generateTests');
                $param = $ref->getParameters()[1] ?? null;
                if ($param && $param->getType() && str_contains((string) $param->getType(), 'array')) {
                    return [
                        'task'    => $task,
                        'matches' => $matched,
                        'summary' => $summary,
                    ];
                }
            }
        } catch (\Throwable $e) {}

        return $summary;
    }

    public function getGraph(): array
    {
        return $this->graph;
    }

    private function loadCachedIndex(): void
    {
        $cacheFile = $this->paths->getCacheDir('wiring.json');
        if (File::exists($cacheFile)) {
            $decoded = json_decode((string) File::get($cacheFile), true);
            if (is_array($decoded)) {
                $this->graph = $decoded;
            }
        }
    }
}
