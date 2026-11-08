<?php

declare(strict_types=1);

namespace Tests\Unit\Tools;

use App\Services\WebSearchService;
use App\Tools\WebSearchTool;
use Mockery;
use Tests\TestCase;

class WebSearchToolTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_delegates_invocation_to_web_search_service(): void
    {
        $mockSearch = Mockery::mock(WebSearchService::class);
        $mockSearch->shouldReceive('search')
            ->with('Neuron AI PHP')
            ->once()
            ->andReturn('Results for Neuron AI PHP');

        $tool = new WebSearchTool($mockSearch);
        $output = $tool('Neuron AI PHP');

        $this->assertSame('Results for Neuron AI PHP', $output);
    }

    public function test_it_has_expected_tool_metadata(): void
    {
        $mockSearch = Mockery::mock(WebSearchService::class);
        $tool = new WebSearchTool($mockSearch);

        $this->assertSame('web_search', $tool->getName());
        $this->assertNotEmpty($tool->getDescription());
    }
}
