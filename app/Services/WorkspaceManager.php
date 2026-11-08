<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class WorkspaceManager
{
    protected AgentPathService $paths;

    public function __construct(?AgentPathService $paths = null)
    {
        $this->paths = $paths ?? app(AgentPathService::class);
    }

    /**
     * Ensure credentials file (.env inside agent directory) is secured in .gitignore.
     */
    public function secureEnvFile(string $gitignorePath, ?string $envRelativePath = null): bool
    {
        $envRelativePath = $envRelativePath ?? $this->paths->getRelativeEnvPath();

        if (!File::exists($gitignorePath)) {
            File::put($gitignorePath, "\n# Ignore AI Agent credentials\n{$envRelativePath}\n");
            return true;
        }

        $content = (string) File::get($gitignorePath);
        if (str_contains($content, $envRelativePath)) {
            return false;
        }

        File::append($gitignorePath, "\n# Ignore AI Agent credentials\n{$envRelativePath}\n");
        return true;
    }
}
