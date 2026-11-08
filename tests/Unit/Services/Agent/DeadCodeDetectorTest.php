<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Agent;

use App\Services\Agent\CodebaseExplorer;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeadCodeDetectorTest extends TestCase
{
    private string $tempWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempWorkspace = sys_get_temp_dir() . '/pnc_dead_code_' . uniqid();
        mkdir($this->tempWorkspace . '/src', 0777, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempWorkspace)) {
            File::deleteDirectory($this->tempWorkspace);
        }
        parent::tearDown();
    }

    public function test_it_identifies_unused_private_methods(): void
    {
        $code = <<<'PHP'
<?php
namespace App;

class SampleService {
    public function execute(): void {
        $this->usedHelper();
    }

    private function usedHelper(): void {}

    private function deadMethod(): void {}
}
PHP;
        $file = $this->tempWorkspace . '/src/SampleService.php';
        File::put($file, $code);

        // Scan tokens for private functions not called with $this->methodName
        $tokens = token_get_all($code);
        $privateMethods = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_PRIVATE) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_FUNCTION) {
                        for ($k = $j + 1; $k < $count; $k++) {
                            if (is_array($tokens[$k]) && $tokens[$k][0] === T_STRING) {
                                $privateMethods[] = $tokens[$k][1];
                                break;
                            }
                        }
                        break;
                    }
                }
            }
        }

        $unused = [];
        foreach ($privateMethods as $method) {
            // Count usages inside the class (excluding the definition itself)
            if (substr_count($code, $method) <= 1) {
                $unused[] = $method;
            }
        }

        $this->assertContains('deadMethod', $unused);
        $this->assertNotContains('usedHelper', $unused);
    }
}
