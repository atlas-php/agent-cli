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
        config()->set('atlas-agent-cli.model.codex', 'gpt-5.1-codex-max');
        config()->set('atlas-agent-cli.reasoning.codex', 'medium');

        /** @var CodexCliSessionService&\Mockery\MockInterface $mockService */
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $this->mockExpectation($mockService, 'startSession')
            ->once()
            ->with(['--model=gpt-5.1-codex-max', '--reasoning=medium', 'tasks:list'], false, 'tasks:list', null, null, null, null, null)
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
        config()->set('atlas-agent-cli.model.codex', null);
        config()->set('atlas-agent-cli.reasoning.codex', 'medium');

        /** @var CodexCliSessionService&\Mockery\MockInterface $mockService */
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $this->mockExpectation($mockService, 'startSession')
            ->once()
            ->with(['--reasoning=medium', 'tasks:list'], false, 'tasks:list', null, null, null, null, null)
            ->andThrow(new \RuntimeException('bad run'));

        $this->app->instance(CodexCliSessionService::class, $mockService);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('codex:session', ['args' => ['tasks:list']]);
        $command
            ->expectsOutput('Codex session failed: bad run')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_command_can_override_model_via_option(): void
    {
        config()->set('atlas-agent-cli.model.codex', 'gpt-5.1-codex-max');
        config()->set('atlas-agent-cli.reasoning.codex', 'medium');

        /** @var CodexCliSessionService&\Mockery\MockInterface $mockService */
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $this->mockExpectation($mockService, 'startSession')
            ->once()
            ->with(['--model=o1-mini', '--reasoning=medium', 'tasks:list'], false, 'tasks:list', null, null, null, null, null)
            ->andReturn([
                'session_id' => 'thread-xyz',
                'json_file_path' => '/tmp/thread-xyz.jsonl',
                'exit_code' => 0,
            ]);

        $this->app->instance(CodexCliSessionService::class, $mockService);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('codex:session', [
            'args' => ['tasks:list'],
            '--model' => 'o1-mini',
        ]);
        $command
            ->expectsOutput('Codex session completed.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_can_override_reasoning_via_option(): void
    {
        config()->set('atlas-agent-cli.model.codex', 'gpt-5.1-codex-max');
        config()->set('atlas-agent-cli.reasoning.codex', 'medium');

        /** @var CodexCliSessionService&\Mockery\MockInterface $mockService */
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $this->mockExpectation($mockService, 'startSession')
            ->once()
            ->with(['--model=gpt-5.1-codex-max', '--reasoning=deep', 'tasks:list'], false, 'tasks:list', null, null, null, null, null)
            ->andReturn([
                'session_id' => 'thread-xyz',
                'json_file_path' => '/tmp/thread-xyz.jsonl',
                'exit_code' => 0,
            ]);

        $this->app->instance(CodexCliSessionService::class, $mockService);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('codex:session', [
            'args' => ['tasks:list'],
            '--reasoning' => 'deep',
        ]);
        $command
            ->expectsOutput('Codex session completed.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_passes_combined_user_arguments_as_initial_input(): void
    {
        config()->set('atlas-agent-cli.model.codex', null);
        config()->set('atlas-agent-cli.reasoning.codex', 'medium');

        /** @var CodexCliSessionService&\Mockery\MockInterface $mockService */
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $this->mockExpectation($mockService, 'startSession')
            ->once()
            ->with(['--reasoning=medium', 'tasks:list', '--plan'], false, 'tasks:list --plan', null, null, null, null, null)
            ->andReturn([
                'session_id' => 'thread-xyz',
                'json_file_path' => '/tmp/thread-xyz.jsonl',
                'exit_code' => 0,
            ]);

        $this->app->instance(CodexCliSessionService::class, $mockService);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('codex:session', ['args' => ['tasks:list', '--plan']]);
        $command->assertExitCode(Command::SUCCESS);
    }

    public function test_command_forwards_workspace_option_to_service(): void
    {
        config()->set('atlas-agent-cli.model.codex', null);
        config()->set('atlas-agent-cli.reasoning.codex', 'medium');

        /** @var CodexCliSessionService&\Mockery\MockInterface $mockService */
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $this->mockExpectation($mockService, 'startSession')
            ->once()
            ->with(['--reasoning=medium', 'tasks:list'], false, 'tasks:list', null, null, null, '/tmp/codex-workspace', null)
            ->andReturn([
                'session_id' => 'thread-xyz',
                'json_file_path' => '/tmp/thread-xyz.jsonl',
                'exit_code' => 0,
            ]);

        $this->app->instance(CodexCliSessionService::class, $mockService);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('codex:session', [
            'args' => ['tasks:list'],
            '--workspace' => '/tmp/codex-workspace',
        ]);
        $command->assertExitCode(Command::SUCCESS);
    }

    public function test_command_forwards_instructions_option(): void
    {
        config()->set('atlas-agent-cli.model.codex', null);
        config()->set('atlas-agent-cli.reasoning.codex', 'medium');

        /** @var CodexCliSessionService&\Mockery\MockInterface $mockService */
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $this->mockExpectation($mockService, 'startSession')
            ->once()
            ->with(['--reasoning=medium', 'tasks:list'], false, 'tasks:list', 'Follow the handbook', null, null, null, null)
            ->andReturn([
                'session_id' => 'thread-xyz',
                'json_file_path' => '/tmp/thread-xyz.jsonl',
                'exit_code' => 0,
            ]);

        $this->app->instance(CodexCliSessionService::class, $mockService);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('codex:session', [
            'args' => ['tasks:list'],
            '--instructions' => 'Follow the handbook',
        ]);
        $command->assertExitCode(Command::SUCCESS);
    }

    public function test_command_forwards_template_options(): void
    {
        config()->set('atlas-agent-cli.model.codex', null);
        config()->set('atlas-agent-cli.reasoning.codex', 'medium');

        /** @var CodexCliSessionService&\Mockery\MockInterface $mockService */
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $this->mockExpectation($mockService, 'startSession')
            ->once()
            ->with(
                ['--reasoning=medium', 'tasks:list'],
                false,
                'tasks:list',
                null,
                null,
                null,
                null,
                [
                    'task' => 'Task: {TASK} [override]',
                    'instructions' => 'Instructions: {INSTRUCTIONS} [override]',
                ]
            )
            ->andReturn([
                'session_id' => 'thread-xyz',
                'json_file_path' => '/tmp/thread-xyz.jsonl',
                'exit_code' => 0,
            ]);

        $this->app->instance(CodexCliSessionService::class, $mockService);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('codex:session', [
            'args' => ['tasks:list'],
            '--template-task' => 'Task: {TASK} [override]',
            '--template-instructions' => 'Instructions: {INSTRUCTIONS} [override]',
        ]);
        $command->assertExitCode(Command::SUCCESS);
    }

    public function test_command_forwards_meta_option(): void
    {
        config()->set('atlas-agent-cli.model.codex', null);
        config()->set('atlas-agent-cli.reasoning.codex', 'medium');

        /** @var CodexCliSessionService&\Mockery\MockInterface $mockService */
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $this->mockExpectation($mockService, 'startSession')
            ->once()
            ->with(['--reasoning=medium', 'tasks:list'], false, 'tasks:list', null, ['assistant_id' => 'assistant-1'], null, null, null)
            ->andReturn([
                'session_id' => 'thread-xyz',
                'json_file_path' => '/tmp/thread-xyz.jsonl',
                'exit_code' => 0,
            ]);

        $this->app->instance(CodexCliSessionService::class, $mockService);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('codex:session', [
            'args' => ['tasks:list'],
            '--meta' => '{"assistant_id":"assistant-1"}',
        ]);
        $command->assertExitCode(Command::SUCCESS);
    }

    public function test_command_fails_when_meta_option_is_invalid_json(): void
    {
        config()->set('atlas-agent-cli.model.codex', null);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('codex:session', [
            'args' => ['tasks:list'],
            '--meta' => '{invalid',
        ]);
        $command
            ->expectsOutput('The --meta option must be valid JSON.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_command_can_resume_existing_thread(): void
    {
        config()->set('atlas-agent-cli.model.codex', null);
        config()->set('atlas-agent-cli.reasoning.codex', 'medium');

        /** @var CodexCliSessionService&\Mockery\MockInterface $mockService */
        $mockService = Mockery::mock(CodexCliSessionService::class);
        $this->mockExpectation($mockService, 'startSession')
            ->once()
            ->with(['--reasoning=medium', 'tasks:list'], false, 'tasks:list', null, null, 'thread-123', null, null)
            ->andReturn([
                'session_id' => 'thread-123',
                'json_file_path' => '/tmp/thread-123.jsonl',
                'exit_code' => 0,
            ]);

        $this->app->instance(CodexCliSessionService::class, $mockService);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('codex:session', [
            'args' => ['tasks:list'],
            '--resume' => 'thread-123',
        ]);
        $command
            ->expectsOutput('Codex session completed.')
            ->assertExitCode(Command::SUCCESS);
    }
}
