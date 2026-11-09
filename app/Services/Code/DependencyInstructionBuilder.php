<?php

declare(strict_types=1);

namespace App\Services\Code;

class DependencyInstructionBuilder
{
    /**
     * @param array<int, string> $dependencies
     */
    public function buildTestMockInstructions(array $dependencies, string $implClass): string
    {
        if (empty($dependencies)) {
            return '';
        }

        $instructions = "### MANDATORY CONSTRUCTOR DEPENDENCIES:\n"
            . "The target class requires these dependencies injected via constructor:\n";

        foreach ($dependencies as $dep) {
            $instructions .= "- {$dep}\n";
        }

        $instructions .= "\nTEST SETUP REQUIREMENTS:\n"
            . "- Import each dependency interface/class with 'use <Namespace>\\<Class>;'.\n"
            . "- In setUp() or test methods, mock each dependency: '\$this->createMock(<DependencyClass>::class)'.\n"
            . "- Pass mocks into 'new {$implClass}(...)' in the constructor.\n"
            . "- Configure expectations and return values on the mocks to test business logic.\n\n";

        return $instructions;
    }

    /**
     * @param array<int, string> $dependencies
     */
    public function buildImplementationInjectionInstructions(array $dependencies): string
    {
        if (empty($dependencies)) {
            return '';
        }

        $instructions = "### CONSTRUCTOR DEPENDENCY INJECTION:\n"
            . "The production class MUST inject and store these dependencies using constructor promotion:\n";

        foreach ($dependencies as $dep) {
            $instructions .= "- {$dep}\n";
        }

        $instructions .= "- Example: 'public function __construct(private readonly <Type> \$var, ...) {}'.\n\n";

        return $instructions;
    }
}
