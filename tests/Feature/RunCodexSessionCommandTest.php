<?php

declare(strict_types=1);

namespace Atlas\Agent\Tests\Feature;

use Atlas\Agent\Services\CodexCliSessionService;
use Atlas\Agent\Tests\TestCase;
use Illuminate\Console\Command;
use Mockery;

/**
 * Class RunCodexSessionCommandTest
 *
 * Validates the artisan command wiring, success handling, and error behavior.
 */
final class RunCodexSessionCommandTest extends TestCase
{
    public function test_command_fails_when_no_arguments_provided(): void
    {
        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('codex:session');
        $command
            ->expectsOutput('You must provide arguments for the Codex CLI.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_command_runs_session_and_prints_summary(): void
    {
        /** @var CodexCliSessionService&\Mockery\MockInterface $mockService */
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $this->mockExpectation($mockService, 'startSession')
            ->once()
            ->with(['tasks:list'], false)
            ->andReturn([
                'session_id' => 'thread-xyz',
                'json_file_path' => '/tmp/thread-xyz.jsonl',
                'exit_code' => 0,
            ]);

        $this->app->instance(CodexCliSessionService::class, $mockService);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('codex:session', ['args' => ['tasks:list']]);
        $command
            ->expectsOutput('Codex session completed.')
            ->expectsOutput('Session ID: thread-xyz')
            ->expectsOutput('JSON log file: /tmp/thread-xyz.jsonl')
            ->expectsOutput('Exit code: 0')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_reports_failure_when_service_throws(): void
    {
        /** @var CodexCliSessionService&\Mockery\MockInterface $mockService */
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $this->mockExpectation($mockService, 'startSession')
            ->once()
            ->with(['tasks:list'], false)
            ->andThrow(new \RuntimeException('bad run'));

        $this->app->instance(CodexCliSessionService::class, $mockService);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('codex:session', ['args' => ['tasks:list']]);
        $command
            ->expectsOutput('Codex session failed: bad run')
            ->assertExitCode(Command::FAILURE);
    }
}
