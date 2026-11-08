<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\AgentContextService;
use App\Services\CompletionService;
use Illuminate\Console\Command;

class CompleteCommand extends Command
{
    protected $signature = 'complete
                            {prompt : Code context or prompt to complete}
                            {--ai-key= : AI provider API key}
                            {--ai-endpoint= : AI provider API endpoint}
                            {--ai-model= : AI model identifier}';

    protected $description = 'Generate inline code completion and log to the daily code log';

    public function handle(CompletionService $completionService, AgentContextService $context): int
    {
        $prompt = (string) $this->argument('prompt');
        $aiKey = $this->option('ai-key');
        $aiEndpoint = $this->option('ai-endpoint');
        $aiModel = $this->option('ai-model');

        $updates = [];
        if ($aiKey !== null) {
            $updates['AI_KEY'] = $aiKey;
        }
        if ($aiEndpoint !== null) {
            $updates['AI_ENDPOINT'] = $aiEndpoint;
        }
        if ($aiModel !== null) {
            $updates['AI_MODEL'] = $aiModel;
        }

        if (!empty($updates)) {
            $context->syncEnvVariables($updates);
        }

        $suggestion = " // generated completion for: " . substr($prompt, 0, 30);
        $completionService->logCompletion($prompt, $suggestion);

        $this->line($suggestion);
        return Command::SUCCESS;
    }
}
