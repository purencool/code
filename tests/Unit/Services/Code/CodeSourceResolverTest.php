<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Code;

use App\Services\Code\CodeSourceResolver;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * @covers \App\Services\CodeCodeSourceResolver
 */
class CodeSourceResolverTest extends TestCase
{
    private CodeSourceResolver $resolver;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CodeSourceResolver();
        $this->tempDir = sys_get_temp_dir() . '/code_resolver_test_' . uniqid();
        File::ensureDirectoryExists($this->tempDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_resolves_code_from_disk_if_target_class_exists(): void
    {
        $filePath = $this->tempDir . '/app/Sample.php';
        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, '<?php class Sample {}');

        $result = $this->resolver->resolve($this->tempDir, 'app/Sample.php', 'ignored task body');

        $this->assertSame('<?php class Sample {}', $result['code']);
        $this->assertSame('app/Sample.php', $result['source_name']);
    }

    public function test_resolves_code_from_markdown_code_fences(): void
    {
        $markdown = "Task:\n```php\nfunction add(): int { return 1 + 1; }\n```";

        $result = $this->resolver->resolve($this->tempDir, null, $markdown);

        $this->assertSame('function add(): int { return 1 + 1; }', $result['code']);
        $this->assertSame('Pasted code block', $result['source_name']);
    }

    public function test_falls_back_to_raw_task_buffer(): void
    {
        $raw = "echo 'Hello World';";

        $result = $this->resolver->resolve($this->tempDir, null, $raw);

        $this->assertSame($raw, $result['code']);
        $this->assertSame('Pasted input buffer', $result['source_name']);
    }
}
