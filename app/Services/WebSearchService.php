<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Throwable;

class WebSearchService
{
    protected AgentContextService $context;
    protected AgentPathService $paths;

    public function __construct(AgentContextService $context, ?AgentPathService $paths = null)
    {
        $this->context = $context;

        if ($paths !== null) {
            $this->paths = $paths;
        } elseif ($context instanceof MockInterface) {
            $this->paths = function_exists('app') && app()->bound(AgentPathService::class)
                ? app(AgentPathService::class)
                : new AgentPathService();
        } else {
            $this->paths = $context->paths();
        }
    }

    /**
     * Execute a web search query.
     */
    public function search(string $query, int $maxResults = 5): string
    {
        if (!$this->context->isWebSearchEnabled()) {
            return "Web search is disabled in {$this->paths->getRelativeConfigPath()} tooling configuration.";
        }

        $apiKey = $this->context->getSearchApiKey();
        $endpoint = $this->context->getSearchEndpoint();

        if (empty($endpoint)) {
            return "Web search failed: SEARCH_ENDPOINT is missing from {$this->paths->getRelativeEnvPath()}.";
        }

        if (empty($apiKey)) {
            return "Web search failed: SEARCH_API_KEY is missing from {$this->paths->getRelativeEnvPath()}.";
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])->post($endpoint, [
                'query'       => $query,
                'max_results' => $maxResults,
            ]);

            if ($response->failed()) {
                return "Web search failed with status {$response->status()}: " . $response->body();
            }

            $data = $response->json();
            if (!is_array($data) || empty($data['results'])) {
                return 'No search results found.';
            }

            return $this->formatResults($query, $data);
        } catch (Throwable $e) {
            return "Web search encountered an exception: {$e->getMessage()}";
        }
    }

    /**
     * Format raw search response into structured markdown.
     *
     * @param array<string, mixed> $response
     */
    public function formatResults(string $query, array $response): string
    {
        $sections = [];
        $sections[] = "### Web Search Results for: \"{$query}\"";

        if (!empty($response['answer'])) {
            $sections[] = (string) $response['answer'];
        }

        $results = $response['results'] ?? [];
        if (!empty($results) && is_array($results)) {
            $resultLines = [];
            foreach ($results as $item) {
                $title = $item['title'] ?? 'Untitled';
                $url = $item['url'] ?? '';
                $content = $item['content'] ?? '';

                $entry = "- [{$title}]({$url})";
                if ($content !== '') {
                    $entry .= "\n  " . $content;
                }
                $resultLines[] = $entry;
            }
            $sections[] = implode("\n\n", $resultLines);
        }

        return implode("\n\n", $sections);
    }
}
