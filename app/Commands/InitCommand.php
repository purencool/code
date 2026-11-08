<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\AgentContextService;
use App\Services\TemplateManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InitCommand extends Command
{
    protected $signature = 'init
                            {name? : The workspace project name or template preset}
                            {--name= : Workspace project name}
                            {--template= : Project template preset (comma-separated for multiple, e.g. php,laravel,nodejs)}
                            {--active-template= : Primary active template when multiple are selected}
                            {--ai-key= : AI provider API key}
                            {--ai-endpoint= : AI provider API endpoint}
                            {--ai-model= : AI model identifier}
                            {--search-key= : Web search API key}
                            {--search-endpoint= : Web search API endpoint}';

    protected $description = 'Initialize agent configuration directory structure and credentials';

    public function handle(AgentContextService $context, TemplateManager $templateManager): int
    {
        $paths = $context->paths();
        $agentDir = $paths->getAgentDir();
        $configFile = $paths->getConfigPath();

        $isInteractive = $this->input->isInteractive();
        if ($this->hasOption('no-interaction') && $this->option('no-interaction')) {
            $isInteractive = false;
        }

        $availableTemplates = $templateManager->getAvailableTemplates();

        if (!File::isDirectory($agentDir)) {
            File::makeDirectory($agentDir, 0755, true);
        }

        $dirName = $paths->getDirectoryName();
        $this->info("--- PncAgent Workspace Initialization ({$dirName}) ---");

        $rawArg = $this->argument('name');
        $nameOption = $this->option('name');
        $templateOption = $this->option('template');

        $projectName = !empty($nameOption) ? (string) $nameOption : null;
        $selectedTemplates = [];
        $defaultTemplate = 'php';

        if (!empty($rawArg)) {
            $argLower = strtolower((string) $rawArg);
            if (in_array($argLower, $availableTemplates, true)) {
                $defaultTemplate = $argLower;
                if (!$isInteractive || !empty($nameOption)) {
                    $selectedTemplates[] = $argLower;
                }
            } elseif ($projectName === null) {
                $projectName = (string) $rawArg;
            }
        }

        if (!empty($templateOption)) {
            $parsed = array_map('trim', explode(',', (string) $templateOption));
            foreach ($parsed as $tpl) {
                $tplLower = strtolower($tpl);
                if (in_array($tplLower, $availableTemplates, true) && !in_array($tplLower, $selectedTemplates, true)) {
                    $selectedTemplates[] = $tplLower;
                }
            }
        }

        if (empty($selectedTemplates)) {
            $chosenPrimary = $isInteractive
                ? (string) $this->choice(
                    'Select workspace project template',
                    $availableTemplates,
                    $defaultTemplate
                )
                : $defaultTemplate;

            $selectedTemplates[] = $chosenPrimary;

            if ($isInteractive) {
                $additional = (string) ($this->ask(
                    'Additional templates to include (comma-separated, leave empty for none)',
                    ''
                ) ?? '');

                if (trim($additional) !== '') {
                    foreach (array_map('trim', explode(',', $additional)) as $tpl) {
                        $tplLower = strtolower($tpl);
                        if (in_array($tplLower, $availableTemplates, true) && !in_array($tplLower, $selectedTemplates, true)) {
                            $selectedTemplates[] = $tplLower;
                        }
                    }
                }
            }
        }

        $activeTemplateOption = $this->option('active-template');
        $activeTemplate = !empty($activeTemplateOption) && in_array(strtolower((string) $activeTemplateOption), $selectedTemplates, true)
            ? strtolower((string) $activeTemplateOption)
            : $selectedTemplates[0];

        if (empty($projectName)) {
            $defaultName = basename($paths->getWorkspaceRoot());
            $projectName = $isInteractive
                ? (string) $this->ask('What is the project name?', $defaultName)
                : $defaultName;
        }

        // Default configurations
        $defaultAiEndpoint = 'http://localhost:11434/v1';
        $defaultAiModel = 'qwen2.5-coder:7b';
        $defaultAiKey = 'ollama';
        $defaultSearchEndpoint = 'https://api.tavily.com/search';
        $defaultSearchKey = '';

        $aiEndpoint = $this->option('ai-endpoint') ?? ($isInteractive ? (string) $this->ask('AI Endpoint URL', $defaultAiEndpoint) : $defaultAiEndpoint);
        $aiModel = $this->option('ai-model') ?? ($isInteractive ? (string) $this->ask('AI Model identifier', $defaultAiModel) : $defaultAiModel);
        $aiKey = $this->option('ai-key') ?? ($isInteractive ? (string) ($this->ask("AI API Key", $defaultAiKey) ?? $defaultAiKey) : $defaultAiKey);
        $searchEndpoint = $this->option('search-endpoint') ?? ($isInteractive ? (string) $this->ask('Web Search Endpoint URL', $defaultSearchEndpoint) : $defaultSearchEndpoint);
        $searchKey = $this->option('search-key') ?? ($isInteractive ? (string) ($this->ask("Web Search API Key (leave empty to configure later in {$paths->getRelativeEnvPath()})", $defaultSearchKey) ?? $defaultSearchKey) : $defaultSearchKey);

        $baseConfig = $templateManager->getBaseConfig();
        $baseConfig['project_name'] = $projectName;
        $baseConfig['ai_settings']['endpoint'] = $aiEndpoint;
        $baseConfig['ai_settings']['model'] = $aiModel;

        $activeConfigPayload = null;

        foreach ($selectedTemplates as $tpl) {
            $tplData = $templateManager->getTemplate($tpl) ?? [];
            $tplConfig = array_merge($baseConfig, $tplData);

            $tplDir = $paths->getTemplateDir($tpl);
            if (!File::isDirectory($tplDir)) {
                File::makeDirectory($tplDir, 0755, true);
            }

            $tplConfigPath = $paths->getConfigPath($tpl);
            File::put($tplConfigPath, (string) json_encode($tplConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info("Created template directory: {$paths->getRelativeConfigPath($tpl)}");

            if ($tpl === $activeTemplate) {
                $activeConfigPayload = $tplConfig;
            }
        }

        if ($activeConfigPayload !== null) {
            File::put($configFile, (string) json_encode($activeConfigPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info("Active workspace profile linked to {$paths->getRelativeConfigPath()} ({$activeTemplate})");
        }

        $context->syncEnvVariables([
            'AGENT_TEMPLATE'  => $activeTemplate,
            'AI_KEY'          => $aiKey,
            'AI_ENDPOINT'     => $aiEndpoint,
            'AI_MODEL'        => $aiModel,
            'SEARCH_API_KEY'  => $searchKey,
            'SEARCH_ENDPOINT' => $searchEndpoint,
        ]);

        $this->info("Synchronized credentials: {$paths->getRelativeEnvPath()}");
        $this->line("<info>Workspace '{$projectName}' initialized successfully with [" . implode(', ', $selectedTemplates) . "]!</info>\n");

        return self::SUCCESS;
    }
}
