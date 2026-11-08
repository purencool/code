<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class AgentContextService
{
    protected AgentPathService $paths;

    public function __construct(?string $baseDir = null, ?AgentPathService $paths = null)
    {
        $this->paths = $paths ?? new AgentPathService($baseDir ?? getcwd());
        if ($baseDir !== null) {
            $this->paths->setWorkspaceRoot($baseDir);
        }
    }

    public function paths(): AgentPathService
    {
        return $this->paths;
    }

    public function getWorkspaceRoot(): string
    {
        return $this->paths->getWorkspaceRoot();
    }

    public function getBaseDir(): string
    {
        return $this->paths->getWorkspaceRoot();
    }

    public function setWorkspaceRoot(string $baseDir): self
    {
        $this->paths->setWorkspaceRoot($baseDir);
        return $this;
    }

    public function getAgentDir(?string $subpath = null): string
    {
        return $this->paths->getAgentDir($subpath);
    }

    public function getEnvPath(): string
    {
        return $this->paths->getEnvPath();
    }

    public function getActiveTemplate(): string
    {
        return $this->paths->getActiveTemplate();
    }

    public function getConfigPath(): string
    {
        $activeTemplate = $this->getActiveTemplate();
        $templateConfig = $this->paths->getConfigPath($activeTemplate);

        if (File::exists($templateConfig)) {
            return $templateConfig;
        }

        return $this->paths->getConfigPath();
    }

    public function load(): array
    {
        $path = $this->getConfigPath();
        if (!File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function switchTemplate(string $templateName): bool
    {
        $templateConfig = $this->paths->getConfigPath($templateName);
        if (!File::exists($templateConfig)) {
            return false;
        }

        File::copy($templateConfig, $this->paths->getConfigPath());

        $this->syncEnvVariables([
            'AGENT_TEMPLATE' => $templateName,
        ]);

        return true;
    }

    public function getEnvVariables(): array
    {
        $envPath = $this->getEnvPath();
        if (!File::exists($envPath)) {
            return [];
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $vars = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $val] = explode('=', $line, 2);
            $vars[trim($key)] = trim(trim($val), "\"'");
        }

        return $vars;
    }

    public function syncEnvVariables(array $variables): void
    {
        $agentDir = $this->getAgentDir();
        if (!File::isDirectory($agentDir)) {
            File::makeDirectory($agentDir, 0755, true);
        }

        $current = $this->getEnvVariables();
        $merged = array_merge($current, array_filter($variables, fn ($v) => $v !== null));

        $lines = [];
        foreach ($merged as $k => $v) {
            $lines[] = "{$k}=\"{$v}\"";
        }

        File::put($this->getEnvPath(), implode("\n", $lines) . "\n");
    }

    public function isWebSearchEnabled(): bool
    {
        $config = $this->load();
        return (bool) ($config['tools']['web_search']['enabled'] ?? false);
    }

    public function getSearchApiKey(): ?string
    {
        $env = $this->getEnvVariables();
        return $env['SEARCH_API_KEY'] ?? null;
    }

    public function getSearchEndpoint(): ?string
    {
        $env = $this->getEnvVariables();
        return $env['SEARCH_ENDPOINT'] ?? null;
    }
}
