<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tdd;

use App\Services\Tdd\PathNamespaceResolver;
use Tests\TestCase;

/**
 * @covers \App\Services\Tdd\PathNamespaceResolver
 */
class PathNamespaceResolverTest extends TestCase
{
    private PathNamespaceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PathNamespaceResolver();
    }

    public function test_resolves_namespaces_from_app_and_test_paths(): void
    {
        $this->assertSame(
            'App\Services\Chat',
            $this->resolver->resolveNamespaceFromPath('app/Services/Chat/ClientService.php', 'Default')
        );
        $this->assertSame(
            'App\Commands',
            $this->resolver->resolveNamespaceFromPath('app/Commands/ClientCommand.php', 'Default')
        );
        $this->assertSame(
            'Tests\Unit\Services\Chat',
            $this->resolver->resolveNamespaceFromPath('tests/Unit/Services/Chat/ClientLoggerTest.php', 'Default')
        );
    }

    public function test_falls_back_to_default_for_unrecognized_paths(): void
    {
        $this->assertSame('DefaultNs', $this->resolver->resolveNamespaceFromPath('.', 'DefaultNs'));
        $this->assertSame('DefaultNs', $this->resolver->resolveNamespaceFromPath('', 'DefaultNs'));
    }

    public function test_resolves_class_name_and_fqcn(): void
    {
        $this->assertSame(
            'ClientService',
            $this->resolver->resolveClassNameFromPath('app/Services/Chat/ClientService.php', 'Fallback')
        );
        $this->assertSame(
            'App\Services\Chat\ClientService',
            $this->resolver->resolveFqcn('app/Services/Chat/ClientService.php')
        );
    }
}
