<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Agent;

use App\Services\Agent\ContextBudgeter;
use Tests\TestCase;

class ContextBudgeterTest extends TestCase
{
    private ContextBudgeter $budgeter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->budgeter = new ContextBudgeter();
    }

    public function test_it_sanitizes_phpunit_stack_trace_removing_vendor_frames(): void
    {
        $verboseTrace = <<<TRACE
PHPUnit 10.5.0 by Sebastian Bergmann and contributors.

There was 1 failure:

1) Tests\Unit\BillingTest::test_charges_account
Failed asserting that 50.0 matches expected 100.0.

#0 /workspace/vendor/laravel/framework/src/Illuminate/Container/Container.php(100): resolve()
#1 /workspace/vendor/laravel/framework/src/Illuminate/Foundation/Application.php(200): boot()
#2 /workspace/app/Services/BillingService.php(45): App\Services\BillingService->calculate()
#3 /workspace/vendor/pestphp/pest/src/Runner.php(50): run()
#4 /workspace/tests/Unit/BillingTest.php(20): test_charges_account()
#5 /workspace/vendor/phpunit/phpunit/src/Framework/TestCase.php(120): runBare()
FAILURES!
TRACE;

        $sanitized = $this->budgeter->sanitizeTestTrace($verboseTrace, maxAppFrames: 3);

        $this->assertStringContainsString('Failed asserting that 50.0 matches expected 100.0.', $sanitized);
        $this->assertStringContainsString('app/Services/BillingService.php(45)', $sanitized);
        $this->assertStringContainsString('tests/Unit/BillingTest.php(20)', $sanitized);

        $this->assertStringNotContainsString('vendor/laravel/framework', $sanitized);
        $this->assertStringNotContainsString('vendor/pestphp/pest', $sanitized);
        $this->assertStringNotContainsString('vendor/phpunit/phpunit', $sanitized);
    }

    public function test_it_truncates_large_output_preserving_head_and_tail_windows(): void
    {
        $lines = [];
        for ($i = 1; $i <= 200; $i++) {
            $lines[] = "Trace log line {$i}: details recorded.";
        }
        $largePayload = implode("\n", $lines);

        $windowed = $this->budgeter->truncateLogWindow($largePayload, headCount: 5, tailCount: 5);

        $resultLines = explode("\n", $windowed);
        $this->assertCount(11, $resultLines);
        $this->assertSame('Trace log line 1: details recorded.', $resultLines[0]);
        $this->assertSame('Trace log line 5: details recorded.', $resultLines[4]);
        $this->assertStringContainsString('[... 190 lines omitted for context budget ...]', $resultLines[5]);
        $this->assertSame('Trace log line 196: details recorded.', $resultLines[6]);
        $this->assertSame('Trace log line 200: details recorded.', $resultLines[10]);
    }

    public function test_it_enforces_token_and_character_ceiling_strictly(): void
    {
        $hugeString = str_repeat('Alphabetical string sequence. ', 500); // ~15,000 characters
        $characterCeiling = 1200;

        $cappedOutput = $this->budgeter->enforceCeiling($hugeString, $characterCeiling);

        $this->assertLessThanOrEqual($characterCeiling + 60, strlen($cappedOutput));
        $this->assertStringContainsString('[TRUNCATED DUE TO CONTEXT BUDGET]', $cappedOutput);
    }
}
