<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Agent;

use App\Services\Agent\CodebaseExplorer;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CodebaseExplorerTest extends TestCase
{
    private string $tempWorkspace;
    private CodebaseExplorer $explorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->explorer = new CodebaseExplorer();
        $this->tempWorkspace = sys_get_temp_dir() . '/pnc_explorer_test_' . uniqid();

        mkdir("{$this->tempWorkspace}/app/Services", 0777, true);
        mkdir("{$this->tempWorkspace}/vendor/laravel/framework", 0777, true);
        mkdir("{$this->tempWorkspace}/node_modules/axios", 0777, true);

        $serviceCode = <<<'PHP'
<?php

namespace App\Services;

class PaymentService
{
    /**
     * Charge credit card via payment gateway.
     */
    public function charge(float $amount, string $currency = 'USD'): bool
    {
        $this->logTransaction($amount);
        return true;
    }

    protected function validateCard(string $cardNumber): bool
    {
        return strlen($cardNumber) === 16;
    }

    private function logTransaction(float $amount): void
    {
        // Internal logging details
    }
}
PHP;
        file_put_contents("{$this->tempWorkspace}/app/Services/PaymentService.php", $serviceCode);
        file_put_contents("{$this->tempWorkspace}/vendor/laravel/framework/Container.php", "<?php class Container { public function charge() {} }");
        file_put_contents("{$this->tempWorkspace}/node_modules/axios/index.js", "function charge() {}");
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempWorkspace)) {
            File::deleteDirectory($this->tempWorkspace);
        }
        parent::tearDown();
    }

    public function test_it_greps_symbol_across_directories_ignoring_blacklisted_paths(): void
    {
        $matches = $this->explorer->grep(
            $this->tempWorkspace,
            'charge',
            ['vendor', 'node_modules', '.git']
        );

        $this->assertCount(1, $matches);
        $this->assertStringContainsString('app/Services/PaymentService.php', $matches[0]['file']);
        $this->assertSame(10, $matches[0]['line']);
        $this->assertStringContainsString('public function charge(float $amount', $matches[0]['text']);
    }

    public function test_it_extracts_public_and_protected_method_signatures_omitting_bodies(): void
    {
        $filePath = "{$this->tempWorkspace}/app/Services/PaymentService.php";

        $signatures = $this->explorer->extractMethodSignatures($filePath);

        $this->assertCount(2, $signatures);
        $this->assertContains('public function charge(float $amount, string $currency = \'USD\'): bool', $signatures);
        $this->assertContains('protected function validateCard(string $cardNumber): bool', $signatures);

        $signaturesText = implode("\n", $signatures);
        $this->assertStringNotContainsString('logTransaction', $signaturesText);
        $this->assertStringNotContainsString('return true;', $signaturesText);
        $this->assertStringNotContainsString('strlen($cardNumber)', $signaturesText);
    }

    public function test_it_extracts_symbol_outline_with_docblocks(): void
    {
        $filePath = "{$this->tempWorkspace}/app/Services/PaymentService.php";

        $outline = $this->explorer->extractOutline($filePath);

        $this->assertArrayHasKey('PaymentService', $outline['classes']);
        $methods = $outline['classes']['PaymentService']['methods'];

        $this->assertArrayHasKey('charge', $methods);
        $this->assertSame('public', $methods['charge']['visibility']);
        $this->assertStringContainsString('Charge credit card via payment gateway.', $methods['charge']['docblock']);
        $this->assertSame('bool', $methods['charge']['return_type']);
        $this->assertArrayHasKey('validateCard', $methods);
        $this->assertSame('protected', $methods['validateCard']['visibility']);
        $this->assertArrayNotHasKey('logTransaction', $methods);
    }
}
