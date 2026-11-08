<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class AgentPathService
{
    public const DEFAULT_DIRECTORY_NAME = '.agent';
    public const DEFAULT_TEMPLATE = 'php';

    protected string $workspaceRoot;
    protected ?string $customDirectoryName = null;
    protected ?string $customTemplate = null;

    public function __construct(
        ?string $workspaceRoot = null,
        ?string $directoryName = null,
        ?string $template = null
    ) {
        $this->workspaceRoot = $workspaceRoot ?? getcwd();
        $this->customDirectoryName = $directoryName;
        $this->customTemplate = $template;
    }

    public function getWorkspaceRoot(): string
    {
        return $this->workspaceRoot;
    }

    public function setWorkspaceRoot(string $workspaceRoot): self
    {
        $this->workspaceRoot = $workspaceRoot;
        return $this;
    }

    public function getDirectoryName(): string
    {
        if ($this->customDirectoryName !== null && $this->customDirectoryName !== '') {
            return trim($this->customDirectoryName, '/\\');
        }

        $envVar = getenv('AGENT_DIR') ?: ($_ENV['AGENT_DIR'] ?? $_SERVER['AGENT_DIR'] ?? null);
        if (!empty($envVar) && is_string($envVar)) {
            return trim($envVar, '/\\');
        }

        $rootEnv = $this->workspaceRoot . '/.env';
        if (File::exists($rootEnv)) {
            $parsed = $this->parseEnvFile($rootEnv);
            if (!empty($parsed['AGENT_DIR'])) {
                return trim($parsed['AGENT_DIR'], '/\\');
            }
        }

        return self::DEFAULT_DIRECTORY_NAME;
    }

    public function setDirectoryName(?string $directoryName): self
    {
        $this->customDirectoryName = $directoryName;
        return $this;
    }

    public function getAgentDir(?string $subpath = null): string
    {
        $base = $this->workspaceRoot . '/' . $this->getDirectoryName();
        if ($subpath !== null && trim($subpath) !== '') {
            return $base . '/' . ltrim($subpath, '/\\');
        }
        return $base;
    }

    public function getRelativePath(?string $subpath = null): string
    {
        $dir = $this->getDirectoryName();
        if ($subpath !== null && trim($subpath) !== '') {
            return $dir . '/' . ltrim($subpath, '/\\');
        }
        return $dir;
    }

    public function getEnvPath(): string
    {
        return $this->getAgentDir('.env');
    }

    public function getRelativeEnvPath(): string
    {
        return $this->getRelativePath('.env');
    }

    public function getActiveTemplate(): string
    {
        if ($this->customTemplate !== null && $this->customTemplate !== '') {
            return trim($this->customTemplate, '/\\');
        }

        $envVar = getenv('AGENT_TEMPLATE') ?: ($_ENV['AGENT_TEMPLATE'] ?? $_SERVER['AGENT_TEMPLATE'] ?? null);
        if (!empty($envVar) && is_string($envVar)) {
            return trim($envVar, '/\\');
        }

        $envPath = $this->getEnvPath();
        if (File::exists($envPath)) {
            $parsed = $this->parseEnvFile($envPath);
            if (!empty($parsed['AGENT_TEMPLATE'])) {
                return trim($parsed['AGENT_TEMPLATE'], '/\\');
            }
        }

        $rootEnv = $this->workspaceRoot . '/.env';
        if (File::exists($rootEnv)) {
            $parsed = $this->parseEnvFile($rootEnv);
            if (!empty($parsed['AGENT_TEMPLATE'])) {
                return trim($parsed['AGENT_TEMPLATE'], '/\\');
            }
        }

        return self::DEFAULT_TEMPLATE;
    }

    public function setActiveTemplate(?string $template): self
    {
        $this->customTemplate = $template;
        return $this;
    }

    public function getTemplateDir(?string $template = null, ?string $subpath = null): string
    {
        $tpl = $template !== null && trim($template) !== '' ? trim($template, '/\\') : $this->getActiveTemplate();
        $base = $this->getAgentDir($tpl);

        if ($subpath !== null && trim($subpath) !== '') {
            return $base . '/' . ltrim($subpath, '/\\');
        }

        return $base;
    }

    public function getRelativeTemplatePath(?string $subpath = null, ?string $template = null): string
    {
        $tpl = $template !== null && trim($template) !== '' ? trim($template, '/\\') : $this->getActiveTemplate();
        if ($subpath !== null && trim($subpath) !== '') {
            return $this->getRelativePath($tpl . '/' . ltrim($subpath, '/\\'));
        }
        return $this->getRelativePath($tpl);
    }

    /**
     * Resolves the root config when no template is passed, or template-specific config when specified.
     */
    public function getConfigPath(?string $template = null): string
    {
        if ($template !== null && trim($template) !== '') {
            return $this->getAgentDir(trim($template, '/\\') . '/config.json');
        }

        return $this->getAgentDir('config.json');
    }

    public function getRelativeConfigPath(?string $template = null): string
    {
        if ($template !== null && trim($template) !== '') {
            return $this->getRelativePath(trim($template, '/\\') . '/config.json');
        }

        return $this->getRelativePath('config.json');
    }

    public function getLogsDir(?string $filename = null, ?string $template = null): string
    {
        $tpl = $template !== null && trim($template) !== '' ? trim($template, '/\\') : $this->getActiveTemplate();
        if ($filename !== null && trim($filename) !== '') {
            return $this->getAgentDir($tpl . '/logs/' . ltrim($filename, '/\\'));
        }
        return $this->getAgentDir($tpl . '/logs');
    }

    public function getRelativeLogsPath(?string $filename = null, ?string $template = null): string
    {
        $tpl = $template !== null && trim($template) !== '' ? trim($template, '/\\') : $this->getActiveTemplate();
        if ($filename !== null && trim($filename) !== '') {
            return $this->getRelativePath($tpl . '/logs/' . ltrim($filename, '/\\'));
        }
        return $this->getRelativePath($tpl . '/logs');
    }

    public function getCacheDir(?string $filename = null, ?string $template = null): string
    {
        $tpl = $template !== null && trim($template) !== '' ? trim($template, '/\\') : $this->getActiveTemplate();
        if ($filename !== null && trim($filename) !== '') {
            return $this->getAgentDir($tpl . '/cache/' . ltrim($filename, '/\\'));
        }
        return $this->getAgentDir($tpl . '/cache');
    }

    public function getRelativeCachePath(?string $filename = null, ?string $template = null): string
    {
        $tpl = $template !== null && trim($template) !== '' ? trim($template, '/\\') : $this->getActiveTemplate();
        if ($filename !== null && trim($filename) !== '') {
            return $this->getRelativePath($tpl . '/cache/' . ltrim($filename, '/\\'));
        }
        return $this->getRelativePath($tpl . '/cache');
    }

    public function getWatchLogPath(?string $template = null): string
    {
        $tpl = $template !== null && trim($template) !== '' ? trim($template, '/\\') : $this->getActiveTemplate();
        return $this->getAgentDir($tpl . '/watch.log');
    }

    public function getRelativeWatchLogPath(?string $template = null): string
    {
        $tpl = $template !== null && trim($template) !== '' ? trim($template, '/\\') : $this->getActiveTemplate();
        return $this->getRelativePath($tpl . '/watch.log');
    }

    public function getPidPath(?string $template = null): string
    {
        $tpl = $template !== null && trim($template) !== '' ? trim($template, '/\\') : $this->getActiveTemplate();
        return $this->getAgentDir($tpl . '/watcher.pid');
    }

    public function getScratchpadPath(?string $template = null): string
    {
        $tpl = $template !== null && trim($template) !== '' ? trim($template, '/\\') : $this->getActiveTemplate();
        return $this->getAgentDir($tpl . '/scratchpad.md');
    }

    public function getRelativeScratchpadPath(?string $template = null): string
    {
        $tpl = $template !== null && trim($template) !== '' ? trim($template, '/\\') : $this->getActiveTemplate();
        return $this->getRelativePath($tpl . '/scratchpad.md');
    }

    protected function parseEnvFile(string $filePath): array
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
}
