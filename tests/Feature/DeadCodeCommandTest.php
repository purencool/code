<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class DeadCodeCommandTest extends TestCase
{
    public function test_it_aborts_with_error_when_given_invalid_path(): void
    {
        $this->artisan('audit:dead-code non_existent_directory_xyz')
            ->expectsOutputToContain('Target path does not exist')
            ->assertExitCode(1);
    }

    public function test_it_executes_dead_code_audit_with_no_fail_flag(): void
    {
        $this->artisan('audit:dead-code app --no-fail')
            ->assertExitCode(0);
    }

    public function test_it_exits_with_failure_code_when_unreferenced_symbols_exist(): void
    {
        $this->artisan('audit:dead-code app')
            ->assertExitCode(1);
    }
}
