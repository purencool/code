<?php

declare(strict_types=1);

namespace App\Services;

use App\Templates\DrupalTemplate;
use App\Templates\LaravelTemplate;
use App\Templates\NodeJsTemplate;
use App\Templates\PhpTemplate;
use App\Templates\PncAgentTemplate;
use App\Templates\TemplateInterface;

class TemplateManager
{
    /** @var array<string, class-string<TemplateInterface>> */
    protected array $templates = [
        'php'      => PhpTemplate::class,
        'laravel'  => LaravelTemplate::class,
        'drupal'   => DrupalTemplate::class,
        'nodejs'   => NodeJsTemplate::class,
        'pncagent' => PncAgentTemplate::class,
    ];

    /**
     * Get a list of all registered template identifiers.
     *
     * @return array<int, string>
     */
    public function getAvailableTemplates(): array
    {
        return array_keys($this->templates);
    }

    public function getBaseConfig(): array
    {
        return [
            'project_name' => 'My PncAgent Workspace',
            'ai_settings' => [
                'endpoint'    => 'https://api.openai.com/v1',
                'model'       => 'gpt-4o',
                'temperature' => 0.2,
            ],
            'tooling' => [
                'allow_web_search'    => true,
                'auto_generate_tests' => true,
                'watch_file_saves'    => true,
            ],
        ];
    }

    public function getTemplate(string $templateName): ?array
    {
        $key = strtolower($templateName);

        if (!isset($this->templates[$key])) {
            return null;
        }

        /** @var TemplateInterface $templateClass */
        $templateClass = new $this->templates[$key]();

        return [
            'stack'            => $templateClass->getStack(),
            'agent_rules'      => $templateClass->getAgentRules(),
            'schemas'          => $templateClass->getSchemas(),
            'scripts'          => $templateClass->getScripts(),
            'context_scanning' => $templateClass->getContextScanning(),
        ];
    }
}
