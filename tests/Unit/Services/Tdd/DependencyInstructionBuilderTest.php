<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tdd;

use App\Services\Tdd\DependencyInstructionBuilder;
use Tests\TestCase;

/**
 * @covers \App\Services\Tdd\DependencyInstructionBuilder
 */
class DependencyInstructionBuilderTest extends TestCase
{
    private DependencyInstructionBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new DependencyInstructionBuilder();
    }

    public function test_builds_mock_setup_instructions_for_tests(): void
    {
        $deps = ['App\Services\AgentContextService', 'Illuminate\Support\Facades\File'];
        $output = $this->builder->buildTestMockInstructions($deps, 'ClientLogger');

        $this->assertStringContainsString('MANDATORY CONSTRUCTOR DEPENDENCIES', $output);
        $this->assertStringContainsString('- App\Services\AgentContextService', $output);
        $this->assertStringContainsString("new ClientLogger(...)", $output);
    }

    public function test_builds_constructor_promotion_instructions_for_implementation(): void
    {
        $deps = ['App\Services\AgentContextService'];
        $output = $this->builder->buildImplementationInjectionInstructions($deps);

        $this->assertStringContainsString('CONSTRUCTOR DEPENDENCY INJECTION', $output);
        $this->assertStringContainsString('constructor promotion', $output);
        $this->assertStringContainsString('- App\Services\AgentContextService', $output);
    }

    public function test_returns_empty_string_when_no_dependencies_provided(): void
    {
        $this->assertSame('', $this->builder->buildTestMockInstructions([], 'SampleClass'));
        $this->assertSame('', $this->builder->buildImplementationInjectionInstructions([]));
    }
}
