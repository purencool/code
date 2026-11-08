<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AgentContextService;
use App\Services\WebSearchService;
use Mockery;
use Tests\TestCase;

class WebSearchServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_returns_notice_when_search_is_disabled(): void
    {
        $mockContext = Mockery::mock(AgentContextService::class);
        $mockContext->shouldReceive('isWebSearchEnabled')->once()->andReturn(false);

        $service = new WebSearchService($mockContext);
        $result = $service->search('test query');

        $this->assertStringContainsString('Web search is disabled', $result);
    }

    public function test_it_returns_warning_when_endpoint_is_missing(): void
    {
        $mockContext = Mockery::mock(AgentContextService::class);
        $mockContext->shouldReceive('isWebSearchEnabled')->once()->andReturn(true);
        $mockContext->shouldReceive('getSearchApiKey')->once()->andReturn('test-key');
        $mockContext->shouldReceive('getSearchEndpoint')->once()->andReturn('');

        $service = new WebSearchService($mockContext);
        $result = $service->search('test query');

        $this->assertStringContainsString('SEARCH_ENDPOINT is missing', $result);
    }

    public function test_it_returns_warning_when_api_key_is_missing(): void
    {
        $mockContext = Mockery::mock(AgentContextService::class);
        $mockContext->shouldReceive('isWebSearchEnabled')->once()->andReturn(true);
        $mockContext->shouldReceive('getSearchEndpoint')->once()->andReturn('https://api.custom-search.internal/v1');
        $mockContext->shouldReceive('getSearchApiKey')->once()->andReturn('');

        $service = new WebSearchService($mockContext);
        $result = $service->search('test query');

        $this->assertStringContainsString('SEARCH_API_KEY is missing', $result);
    }

    public function test_it_formats_search_results_cleanly(): void
    {
        $mockContext = Mockery::mock(AgentContextService::class);
        $service = new WebSearchService($mockContext);

        $rawResponse = [
            'answer' => 'Laravel Zero is a micro-framework for console applications.',
            'results' => [
                [
                    'title' => 'Laravel Zero Documentation',
                    'url' => 'https://laravel-zero.com',
                    'content' => 'Build CLI apps with the simplicity of Laravel.',
                ],
            ],
        ];

        $formatted = $service->formatResults('Laravel Zero', $rawResponse);

        $this->assertStringContainsString('Web Search Results for: "Laravel Zero"', $formatted);
        $this->assertStringContainsString('Laravel Zero is a micro-framework', $formatted);
        $this->assertStringContainsString('[Laravel Zero Documentation](https://laravel-zero.com)', $formatted);
        $this->assertStringContainsString('Build CLI apps with the simplicity', $formatted);
    }
}
