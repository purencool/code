<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Templates\PhpTemplate;
use PHPUnit\Framework\TestCase;

class PhpTemplateTest extends TestCase
{
    private PhpTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->template = new PhpTemplate();
    }

    public function test_it_returns_php_stack_and_context_scanning(): void
    {
        $stack = $this->template->getStack();
        $this->assertSame('php', $stack['primary_language']);

        $rules = $this->template->getAgentRules();
        $this->assertNotEmpty($rules);

        $scripts = $this->template->getScripts();
        $this->assertArrayHasKey('test', $scripts);

        $scanning = $this->template->getContextScanning();
        $this->assertContains('src', $scanning['include_dirs']);
        $this->assertContains('php', $scanning['file_extensions']);
    }
}
