<?php

declare(strict_types=1);

namespace App\Services\Testing;

class TestResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly string $output,
        public readonly string $errorOutput,
        public readonly string $command,
    ) {
    }
}
