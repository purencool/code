<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TemplateManager;
use PHPUnit\Framework\TestCase;

class TemplateManagerTest extends TestCase
{
    private TemplateManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new TemplateManager();
    }

    public function test_it_returns_base_config(): void
    {
        $config = $this->manager->getBaseConfig();

        $this->assertArrayHasKey('project_name', $config);
        $this->assertArrayHasKey('ai_settings', $config);
        $this->assertTrue($config['tooling']['watch_file_saves']);
    }

    public function test_it_resolves_valid_templates(): void
    {
        $laravelTemplate = $this->manager->getTemplate('laravel');
        $this->assertNotNull($laravelTemplate);
        $this->assertEquals('php', $laravelTemplate['stack']['primary_language']);

        $pncAgentTemplate = $this->manager->getTemplate('pncagent');
        $this->assertNotNull($pncAgentTemplate);
        $this->assertArrayHasKey('laravel-zero', $pncAgentTemplate['stack']['frameworks']);
    }

    public function test_it_handles_case_insensitivity(): void
    {
        $template = $this->manager->getTemplate('NODEJS');
        $this->assertNotNull($template);
        $this->assertEquals('javascript', $template['stack']['primary_language']);
    }

    public function test_it_returns_null_for_invalid_template(): void
    {
        $template = $this->manager->getTemplate('unknown_framework');
        $this->assertNull($template);
    }
}
