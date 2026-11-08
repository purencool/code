<?php

declare(strict_types=1);

namespace App\Tools;

use App\Services\WebSearchService;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class WebSearchTool extends Tool
{
    protected string $name = 'web_search';
    protected ?string $description = 'Search the web for up-to-date documentation, external libraries, API definitions, or syntax when local context is insufficient.';

    public function __construct(
        protected readonly WebSearchService $searchService
    ) {}

    /**
     * Define the schema properties for LLM tool selection.
     *
     * @return array<int, ToolProperty>
     */
    protected function properties(): array
    {
        $stringType = class_exists(PropertyType::class) ? PropertyType::STRING : 'string';

        return [
            new ToolProperty(
                name: 'query',
                type: $stringType,
                description: 'The search query or keywords to look up on the web.',
                required: true
            ),
        ];
    }

    /**
     * Execute tool action when invoked by Neuron AI agent.
     */
    public function __invoke(string $query): string
    {
        return $this->searchService->search($query);
    }
}
