<?php

declare(strict_types=1);

namespace App\Agents;

use App\Services\AgentContextService;
use App\Services\AI\AIProviderFactory;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;

class ChatAgent extends Agent
{
    public function __construct(
        protected readonly AgentContextService $contextService,
        protected ?AIProviderInterface $customProvider = null
    ) {
    }

    protected function provider(): AIProviderInterface
    {
        if ($this->customProvider !== null) {
            return $this->customProvider;
        }

        return AIProviderFactory::create();
    }

    public function getSystemInstructions(): string
    {
        $root = $this->contextService->getWorkspaceRoot();

        return "You are a software engineering assistant working in the project at '{$root}'. "
            . "Provide direct, concise, and technically accurate responses.";
    }

    protected function instructions(): string
    {
        return $this->getSystemInstructions();
    }

    public function send(string $message): string
    {
        $response = $this->chat(new UserMessage($message));

        return (string) $response->getContent();
    }
}
