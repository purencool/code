<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Agent;

use App\Services\Agent\DiffPatcher;
use Illuminate\Support\Facades\File;
use OutOfBoundsException;
use RuntimeException;
use Tests\TestCase;

class DiffPatcherTest extends TestCase
{
    private string $tempWorkspace;
    private DiffPatcher $patcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->patcher = new DiffPatcher();
        $this->tempWorkspace = sys_get_temp_dir() . '/pnc_diff_test_' . uniqid();
        mkdir($this->tempWorkspace, 0777, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempWorkspace)) {
            File::deleteDirectory($this->tempWorkspace);
        }
        parent::tearDown();
    }

    public function test_it_reads_specific_line_range_from_file(): void
    {
        $filePath = "{$this->tempWorkspace}/SampleClass.php";
        $lines = [];
        for ($i = 1; $i <= 50; $i++) {
            $lines[] = "Line {$i}: Content data";
        }
        File::put($filePath, implode("\n", $lines));

        $result = $this->patcher->readLines($filePath, 10, 15);

        $this->assertCount(6, $result);
        $this->assertSame('Line 10: Content data', $result[10]);
        $this->assertSame('Line 15: Content data', $result[15]);
        $this->assertArrayNotHasKey(9, $result);
        $this->assertArrayNotHasKey(16, $result);
    }

    public function test_it_throws_out_of_bounds_exception_for_invalid_line_range(): void
    {
        $filePath = "{$this->tempWorkspace}/ShortFile.php";
        File::put($filePath, "Line 1\nLine 2\nLine 3");

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Requested line range (10-20) exceeds file line count (3).');

        $this->patcher->readLines($filePath, 10, 20);
    }

    public function test_it_applies_targeted_search_and_replace_block(): void
    {
        $filePath = "{$this->tempWorkspace}/OrderService.php";
        $initialCode = <<<'PHP'
<?php

namespace App\Services;

class OrderService
{
    public function process(int $orderId): bool
    {
        $order = $this->find($orderId);
        return $order !== null;
    }

    public function cancel(int $orderId): void
    {
        // Unmodified critical method
    }
}
PHP;
        File::put($filePath, $initialCode);

        $searchBlock = <<<'SEARCH'
    public function process(int $orderId): bool
    {
        $order = $this->find($orderId);
        return $order !== null;
    }
SEARCH;

        $replaceBlock = <<<'REPLACE'
    public function process(int $orderId): bool
    {
        $order = $this->find($orderId);
        if ($order === null) {
            return false;
        }
        return $order->isProcessed();
    }
REPLACE;

        $this->patcher->applySearchReplace($filePath, $searchBlock, $replaceBlock);

        $updatedContent = File::get($filePath);
        $this->assertStringContainsString('$order->isProcessed();', $updatedContent);
        $this->assertStringContainsString('public function cancel(int $orderId): void', $updatedContent);
        $this->assertStringContainsString('// Unmodified critical method', $updatedContent);
    }

    public function test_it_throws_runtime_exception_when_search_anchor_is_not_found(): void
    {
        $filePath = "{$this->tempWorkspace}/Target.php";
        File::put($filePath, "<?php\nclass Target {}\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Search block not found in file: target anchor does not match.');

        $this->patcher->applySearchReplace($filePath, 'nonExistentMethod()', 'newMethod()');
    }

    public function test_it_throws_runtime_exception_when_search_anchor_is_ambiguous(): void
    {
        $filePath = "{$this->tempWorkspace}/Ambiguous.php";
        $initialContent = <<<'PHP'
<?php
class Ambiguous {
    public function duplicate(): void { return; }
    public function other(): void {}
    public function duplicate(): void { return; }
}
PHP;
        File::put($filePath, $initialContent);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Search block matched 2 times. Target anchor must match uniquely.');

        $this->patcher->applySearchReplace(
            $filePath,
            '    public function duplicate(): void { return; }',
            '    public function replaced(): void {}'
        );
    }

    public function test_it_applies_unified_diff_hunk_accurately(): void
    {
        $filePath = "{$this->tempWorkspace}/Patcher.php";
        $initialContent = "line 1\nline 2\nline 3\nline 4\nline 5";
        File::put($filePath, $initialContent);

        $unifiedDiff = <<<DIFF
@@ -2,3 +2,4 @@
 line 2
-line 3
+line 3 modified
+line 3.5 added
 line 4
DIFF;

        $this->patcher->applyUnifiedDiff($filePath, $unifiedDiff);

        $updatedContent = File::get($filePath);
        $this->assertStringContainsString("line 3 modified\nline 3.5 added", $updatedContent);
        $this->assertStringNotContainsString("line 3\nline 4", $updatedContent);
    }
}
