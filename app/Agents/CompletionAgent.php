<?php

declare(strict_types=1);

namespace App\Agents;

use App\Services\AgentContextService;
use App\Tools\WebSearchTool;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;
use Throwable;

class CompletionAgent extends Agent
{
    public function __construct(
        protected readonly AgentContextService $agentContext,
        protected readonly WebSearchTool $webSearchTool
    ) {
        parent::__construct();
    }

    protected function provider(): AIProviderInterface
    {
        $settings = $this->agentContext->getAiSettings();
        $apiKey = $this->agentContext->getApiKey();
        $endpoint = rtrim($settings['endpoint'] ?? 'https://api.openai.com/v1', '/');
        $model = (string) ($settings['model'] ?? 'gpt-4o');

        if ($endpoint === '' || str_contains($endpoint, 'api.openai.com')) {
            return new OpenAI(
                key: $apiKey,
                model: $model,
            );
        }

        if (class_exists(OpenAILike::class)) {
            return new OpenAILike(
                key: $apiKey,
                model: $model,
                baseUri: $endpoint,
            );
        }

        return new OpenAI(
            key: $apiKey,
            model: $model,
        );
    }

    public function instructions(): string
    {
        return $this->agentContext->buildSystemPrompt('completion');
    }

    /**
     * Register available tools on the agent.
     */
    protected function tools(): array
    {
        if (!$this->agentContext->isWebSearchEnabled()) {
            return [];
        }

        return [
            $this->webSearchTool,
        ];
    }

    public function completeAt(string $prefix, string $suffix): string
    {
        $userPrompt = "<PRE>{$prefix}<SUF>{$suffix}<MID>";

        try {
            $response = $this->chat(new UserMessage($userPrompt));

            $raw = method_exists($response, 'getMessage')
                ? $response->getMessage()->getContent()
                : (method_exists($response, 'getContent') ? $response->getContent() : (string) $response);

            return is_string($raw) ? trim($raw, "\r\n") : '';
        } catch (Throwable) {
            return '';
        }
    }
}
