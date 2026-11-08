<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Agent;

use App\Services\Agent\SelfCorrectionService;
use Tests\TestCase;

class SelfCorrectionLoopTest extends TestCase
{
    private SelfCorrectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SelfCorrectionService();
    }

    public function test_it_passes_lint_check_for_valid_php_code(): void
    {
        $code = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services;

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}
PHP;

        $result = $this->service->lint($code);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
        $this->assertNull($result['line']);
    }

    public function test_it_fails_lint_check_and_extracts_syntax_error_line(): void
    {
        $brokenCode = <<<'PHP'
<?php

declare(strict_types=1);

class BrokenSyntax
{
    public function broken() {
        return $a + ;
    }
}
PHP;

        $result = $this->service->lint($brokenCode);

        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('syntax error', strtolower((string) $result['error']));
        // In PHP, "return $a + ;" fails on line 8
        $this->assertSame(8, $result['line']);
    }

    public function test_it_extracts_assertion_failure_and_file_from_phpunit_output(): void
    {
        $rawPhpUnitOutput = <<<OUTPUT
PHPUnit 10.5.0 by Sebastian Bergmann and contributors.

F.                                                                  2 / 2 (100%)

There was 1 failure:

1) Tests\Unit\PriceCalculatorTest::test_it_applies_member_discount
Failed asserting that 95.0 matches expected 90.0.

/workspace/project/tests/Unit/PriceCalculatorTest.php:32

FAILURES!
Tests: 2, Assertions: 2, Failures: 1.
OUTPUT;

        $failure = $this->service->extractTestFailure($rawPhpUnitOutput);

        $this->assertNotNull($failure);
        $this->assertSame('95.0 matches expected 90.0.', $failure['assertion']);
        $this->assertSame('/workspace/project/tests/Unit/PriceCalculatorTest.php', $failure['file']);
        $this->assertSame(32, $failure['line']);
    }

    public function test_it_reprompts_immediately_on_syntax_error_without_running_tests(): void
    {
        $testRunnerInvoked = false;

        $generator = function (?string $feedback, int $attempt): string {
            if ($attempt === 1) {
                return '<?php class Incomplete { public function missingBrace() ';
            }

            return '<?php class Complete { public function valid(): bool { return true; } }';
        };

        $verifier = function (string $code) use (&$testRunnerInvoked): array {
            $testRunnerInvoked = true;
            return ['passed' => true, 'output' => 'OK (1 test, 1 assertion)'];
        };

        $outcome = $this->service->runLoop($generator, $verifier, maxRetries: 3);

        $this->assertTrue($outcome['success']);
        $this->assertSame(2, $outcome['attempts']);
        $this->assertTrue($testRunnerInvoked);
        $this->assertSame('syntax_error', $outcome['history'][0]['type']);
        $this->assertStringContainsString('Syntax Error', $outcome['history'][0]['message']);
    }

    public function test_it_self_heals_when_subsequent_retry_passes_tests_within_budget(): void
    {
        $generator = function (?string $feedback, int $attempt): string {
            if ($attempt === 1) {
                $this->assertNull($feedback);
                return '<?php class Discount { public function get(): float { return 100.0; } }';
            }

            $this->assertNotNull($feedback);
            $this->assertStringContainsString('Failed asserting that 100.0 matches expected 90.0', $feedback);

            return '<?php class Discount { public function get(): float { return 90.0; } }';
        };

        $verifier = function (string $code): array {
            if (str_contains($code, '100.0')) {
                return [
                    'passed' => false,
                    'output' => "Failed asserting that 100.0 matches expected 90.0.\n/workspace/tests/DiscountTest.php:14\nFAILURES!",
                ];
            }

            return [
                'passed' => true,
                'output' => 'OK (1 test, 1 assertion)',
            ];
        };

        $outcome = $this->service->runLoop($generator, $verifier, maxRetries: 3);

        $this->assertTrue($outcome['success']);
        $this->assertSame(2, $outcome['attempts']);
        $this->assertSame('test_failure', $outcome['history'][0]['type']);
        $this->assertStringContainsString('90.0', (string) $outcome['final_code']);
    }

    public function test_it_halts_and_reports_error_when_retry_budget_is_exhausted(): void
    {
        $attemptsTracked = 0;

        $generator = function (?string $feedback, int $attempt) use (&$attemptsTracked): string {
            $attemptsTracked = $attempt;
            return '<?php class Stub { public function run(): int { return -1; } }';
        };

        $verifier = function (string $code): array {
            return [
                'passed' => false,
                'output' => "Failed asserting that -1 matches expected 1.\n/workspace/tests/StubTest.php:20\nFAILURES!",
            ];
        };

        $outcome = $this->service->runLoop($generator, $verifier, maxRetries: 3);

        $this->assertFalse($outcome['success']);
        $this->assertSame(3, $outcome['attempts']);
        $this->assertSame(3, $attemptsTracked);
        $this->assertNull($outcome['final_code']);
        $this->assertCount(3, $outcome['history']);
        $this->assertStringContainsString('Failed asserting that -1 matches expected 1', (string) $outcome['error']);
    }
}
