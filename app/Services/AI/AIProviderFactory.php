<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AgentContextService;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Ollama\Ollama;
use RuntimeException;

class AIProviderFactory
{
    public function __construct(
        protected AgentContextService $context
    ) {}

    public function make(?AIProviderInterface $override = null): AIProviderInterface
    {
        if ($override !== null) {
            return $override;
        }

        $config = $this->context->load();
        $driver = $config['ai']['driver'] ?? 'ollama';

        return match ($driver) {
            'ollama' => $this->createOllamaProvider($config),
            default  => throw new RuntimeException("Unsupported AI driver: {$driver}"),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createOllamaProvider(array $config): AIProviderInterface
    {
        $rawUrl = $config['ai']['base_url'] ?? env('OLLAMA_BASE_URL', 'http://localhost:11434');

        $baseUrl = rtrim($rawUrl, '/');
        if (!str_ends_with($baseUrl, '/api')) {
            $baseUrl .= '/api';
        }

        $model = $config['ai']['model'] ?? env('AI_MODEL', 'qwen2.5-coder:7b');

        // Context size and deterministic sampling for pure code generation
        $numCtx      = (int)   ($config['ai']['num_ctx']     ?? env('OLLAMA_NUM_CTX', 8192));
        $temperature = (float) ($config['ai']['temperature'] ?? env('AI_TEMPERATURE', 0.1));
        $topP        = (float) ($config['ai']['top_p']       ?? env('AI_TOP_P', 0.1));
        $topK        = (int)   ($config['ai']['top_k']       ?? env('AI_TOP_K', 20));

        return new Ollama(
            url: $baseUrl,
            model: $model,
            parameters: [
                'num_ctx'     => $numCtx,
                'temperature' => $temperature,
                'top_p'       => $topP,
                'top_k'       => $topK,
            ]
        );
    }
}
