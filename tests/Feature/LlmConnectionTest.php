<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AgentContextService;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;
use Tests\TestCase;
use Throwable;

class LlmConnectionTest extends TestCase
{
    public function test_it_successfully_connects_to_configured_llm_endpoint_via_neuron(): void
    {
        /** @var AgentContextService $contextService */
        $contextService = app(AgentContextService::class);
        $env = $contextService->getEnvVariables();
        $config = $contextService->load();

        $apiKey = $env['AI_KEY'] ?? getenv('AI_KEY') ?: '';
        $endpoint = rtrim(
                $env['AI_ENDPOINT'] ?? $config['ai_settings']['endpoint'] ?? getenv('AI_ENDPOINT') ?: 'https://api.openai.com/v1',
            '/'
        );
        $model = $env['AI_MODEL'] ?? $config['ai_settings']['model'] ?? getenv('AI_MODEL') ?: 'codellama:latest';

        // Allow local LAN / loopback endpoints (like Ollama) even without a standard API key
        $isLocalEndpoint = str_contains($endpoint, 'localhost')
            || str_contains($endpoint, '127.0.0.1')
            || str_contains($endpoint, '192.168.');

        if (!$isLocalEndpoint && (empty($apiKey) || str_starts_with($apiKey, 'sk-test-'))) {
            $this->markTestSkipped('Skipping LLM live connection test: AI_KEY is missing or using a test stub in .agent/.env');
        }

        if ($isLocalEndpoint && empty($apiKey)) {
            $apiKey = 'ollama';
        }

        // Anonymous test agent wired to NeuronAI
        $pingAgent = new class($apiKey, $endpoint, $model) extends Agent {
            public function __construct(
                protected string $key,
                protected string $endpoint,
                protected string $model
            ) {
            }

            protected function provider(): AIProviderInterface
            {
                if (str_contains($this->endpoint, 'openai.com')) {
                    return new OpenAI(key: $this->key, model: $this->model);
                }

                return new OpenAILike(key: $this->key, model: $this->model, baseUri: $this->endpoint);
            }

            protected function instructions(): string
            {
                return 'You are an echo test agent. Reply concisely as requested.';
            }
        };

        try {
            // Bind a thread ID before chatting
            $response = $pingAgent
                ->setThreadId('connection_test_' . uniqid())
                ->chat(new UserMessage("Reply with exactly the single word: PONG"));

            // Resolve message content from Neuron AI response
            $message = method_exists($response, 'getMessage') ? $response->getMessage() : $response;
            $content = method_exists($message, 'getContent') ? $message->getContent() : (string) $message;

            $this->assertNotEmpty($content, "LLM returned an empty response.");
            $this->assertStringContainsStringIgnoringCase(
                'PONG',
                $content,
                "LLM response did not contain expected token 'PONG'. Received: {$content}"
            );
        } catch (Throwable $e) {
            $this->fail(sprintf(
                "LLM connection failed via NeuronAI to endpoint [%s] using model [%s]. Error: %s",
                $endpoint,
                $model,
                $e->getMessage()
            ));
        }
    }
}
