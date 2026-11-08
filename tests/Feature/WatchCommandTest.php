<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\FileWatcherService;
use Mockery\MockInterface;
use Tests\TestCase;

class WatchCommandTest extends TestCase
{
    public function test_it_requires_either_start_or_stop_flag(): void
    {
        $this->artisan('watch')
            ->assertExitCode(2);
    }

    public function test_it_stops_active_watcher_process(): void
    {
        $this->mock(FileWatcherService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getRunningPid')
                ->once()
                ->andReturn(12345);

            $mock->shouldReceive('stopProcess')
                ->with(12345)
                ->once()
                ->andReturn(true);

            $mock->shouldReceive('clearPid')
                ->once();
        });

        $this->artisan('watch --stop')
            ->assertExitCode(0);
    }

    public function test_it_informs_when_stopping_non_existent_process(): void
    {
        $this->mock(FileWatcherService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getRunningPid')
                ->once()
                ->andReturn(null);

            $mock->shouldReceive('clearPid')
                ->once();
        });

        $this->artisan('watch --stop')
            ->assertExitCode(0);
    }

    public function test_it_prevents_starting_if_already_running(): void
    {
        $this->mock(FileWatcherService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getRunningPid')
                ->once()
                ->andReturn(12345);
        });

        $this->artisan('watch --start')
            ->assertExitCode(1);
    }
}
