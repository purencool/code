<?php

declare(strict_types=1);

namespace App\Tools;

use App\Services\CodebaseIndexService;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class WiringContextTool extends Tool
{
    protected string $name = 'get_wiring_context';
    protected ?string $description = 'Inspect application architectural wiring, declarations, imports, and dependent services for a file or component.';

    public function __construct(
        protected readonly CodebaseIndexService $indexService
    ) {}

    /**
     * @return array<int, ToolProperty>
     */
    protected function properties(): array
    {
        $stringType = class_exists(PropertyType::class) ? PropertyType::STRING : 'string';

        return [
            new ToolProperty(
                name: 'filePath',
                type: $stringType,
                description: 'The relative file path or component name to inspect for wiring connections.',
                required: true
            ),
        ];
    }

    public function __invoke(string $filePath): string
    {
        $context = $this->indexService->getNeighborhoodContext($filePath);
        return $context !== '' ? $context : "No wiring relationships found for {$filePath}.";
    }
}
