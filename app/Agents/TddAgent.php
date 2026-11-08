<?php

declare(strict_types=1);

namespace App\Agents;

use App\Services\AgentContextService;
use App\Services\CodebaseIndexService;
use App\Tools\WebSearchTool;
use App\Tools\WiringContextTool;
use NeuronAI\Agent\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;

class TddAgent extends Agent
{
    public function __construct(
        protected AgentContextService $context,
        protected CodebaseIndexService $indexer,
        protected WebSearchTool $webSearchTool,
        protected WiringContextTool $wiringTool
    ) {
    }

    protected function provider(): AIProviderInterface
    {
        $env = $this->context->getEnvVariables();
        $config = $this->context->load();

        $key = $env['AI_KEY'] ?? '';
        $model = $env['AI_MODEL'] ?? $config['ai_settings']['model'] ?? 'gpt-4o';
        $endpoint = rtrim($env['AI_ENDPOINT'] ?? $config['ai_settings']['endpoint'] ?? 'https://api.openai.com/v1', '/');

        if (str_contains($endpoint, 'openai.com')) {
            return new OpenAI(key: $key, model: $model);
        }

        return new OpenAILike(key: $key, model: $model, baseUri: $endpoint);
    }

    protected function instructions(): string
    {
        $config = $this->context->load();
        $rules = implode("\n", $config['agent_rules'] ?? []);
        $stack = json_encode($config['stack'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<INSTRUCTIONS
You are an expert software engineer orchestrating strict Test-Driven Development (TDD).
You have access to tools to inspect codebase wiring and search documentation if needed.

### STACK:
{$stack}

### ARCHITECTURAL RULES:
{$rules}
INSTRUCTIONS;
    }

    protected function tools(): array
    {
        $config = $this->context->load();
        $tools = [$this->wiringTool];

        if ($config['tooling']['allow_web_search'] ?? false) {
            $tools[] = $this->webSearchTool;
        }

        return $tools;
    }
}
