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
    public function testCommandFailsWhenNoArgumentsProvided(): void
    {
        $this->artisan('codex:session')
            ->expectsOutput('You must provide arguments for the Codex CLI.')
            ->assertExitCode(Command::FAILURE);
    }

    public function testCommandRunsSessionAndPrintsSummary(): void
    {
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $mockService->shouldReceive('startSession')
            ->once()
            ->with(['tasks:list'], false)
            ->andReturn([
                'session_id' => 'thread-xyz',
                'json_file_path' => '/tmp/thread-xyz.jsonl',
                'exit_code' => 0,
            ]);

        $this->app->instance(CodexCliSessionService::class, $mockService);

        $this->artisan('codex:session', ['args' => ['tasks:list']])
            ->expectsOutput('Codex session completed.')
            ->expectsOutput('Session ID: thread-xyz')
            ->expectsOutput('JSON log file: /tmp/thread-xyz.jsonl')
            ->expectsOutput('Exit code: 0')
            ->assertExitCode(Command::SUCCESS);
    }

    public function testCommandReportsFailureWhenServiceThrows(): void
    {
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $mockService->shouldReceive('startSession')
            ->once()
            ->with(['tasks:list'], false)
            ->andThrow(new \RuntimeException('bad run'));

        $this->app->instance(CodexCliSessionService::class, $mockService);

        $this->artisan('codex:session', ['args' => ['tasks:list']])
            ->expectsOutput('Codex session failed: bad run')
            ->assertExitCode(Command::FAILURE);
    }
}
