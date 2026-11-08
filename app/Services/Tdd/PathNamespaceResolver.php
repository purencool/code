<?php

declare(strict_types=1);

namespace App\Services\Tdd;

class PathNamespaceResolver
{
    public function resolveNamespaceFromPath(string $path, string $default): string
    {
        $dir = dirname($path);
        if ($dir === '.' || $dir === '') {
            return $default;
        }

        if (str_starts_with($dir, 'app/')) {
            return 'App\\' . str_replace('/', '\\', substr($dir, 4));
        }
        if (str_starts_with($dir, 'app')) {
            return 'App';
        }
        if (str_starts_with($dir, 'tests/')) {
            $sub = substr($dir, 6);
            return 'Tests\\' . ucfirst(str_replace('/', '\\', $sub));
        }

        return $default;
    }

    public function resolveClassNameFromPath(string $path, string $default): string
    {
        $filename = basename($path);
        if (str_ends_with($filename, '.php')) {
            return substr($filename, 0, -4);
        }

        return $default;
    }

    public function resolveFqcn(string $path, string $defaultNs = 'App\\Services', string $defaultClass = 'GeneratedClass'): string
    {
        $ns = $this->resolveNamespaceFromPath($path, $defaultNs);
        $class = $this->resolveClassNameFromPath($path, $defaultClass);

        return $ns . '\\' . $class;
    }
}
