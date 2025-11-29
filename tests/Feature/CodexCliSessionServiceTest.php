<?php

declare(strict_types=1);

namespace Atlas\Agent\Tests\Feature;

use Atlas\Agent\Tests\Support\TestCodexCliSessionService;
use Atlas\Agent\Tests\TestCase;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Class CodexCliSessionServiceTest
 *
 * Exercises the Codex CLI session service with deterministic Symfony Process stubs.
 */
final class CodexCliSessionServiceTest extends TestCase
{
    public function test_headless_session_streams_output_and_stores_log(): void
    {
        $this->cleanCodexDirectory();

        $service = new TestCodexCliSessionService;
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-123'],
            ['type' => 'item.started', 'item' => ['type' => 'command_execution', 'command' => 'ls -la', 'id' => 'item-1']],
            ['type' => 'item.completed', 'item' => ['type' => 'command_execution', 'id' => 'item-1', 'aggregated_output' => "output\r\nline", 'exit_code' => 0]],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 1200, 'output_tokens' => 345]],
        ];

        $this->mockExpectation($process, 'setTimeout')
            ->once()
            ->with(null)
            ->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')
            ->once()
            ->with(null)
            ->andReturnSelf();
        $this->mockExpectation($process, 'run')
            ->once()
            ->andReturnUsing(function (callable $callback) use ($events): int {
                foreach ($events as $event) {
                    $callback(Process::OUT, json_encode($event)."\n");
                }

                return 0;
            });
        $this->mockExpectation($process, 'isSuccessful')
            ->once()
            ->andReturn(true);
        $this->mockExpectation($process, 'getExitCode')
            ->once()
            ->andReturn(0);

        $service->headlessProcess = $process;

        $result = $service->startSession(['tasks:list'], false, 'tasks:list', null);
        $output = $service->streamedOutput[Process::OUT] ?? '';

        $this->assertSame('thread-123', $result['session_id']);
        $this->assertSame(0, $result['exit_code']);

        $expectedDirectory = (string) config('atlas-agent-cli.sessions.path');
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-123.jsonl';
        $this->assertSame($expectedPath, $result['json_file_path']);
        $this->assertFileExists($expectedPath);

        $logContents = file_get_contents($expectedPath);
        $this->assertIsString($logContents);
        $this->assertStringContainsString('thread.started', $logContents);
        $lines = array_values(array_filter(explode("\n", $logContents)));
        $firstEvent = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($firstEvent);
        $this->assertSame('thread.request', $firstEvent['type'] ?? null);
        $this->assertSame('tasks:list', $firstEvent['task'] ?? null);
        $this->assertArrayHasKey('instructions', $firstEvent);
        $this->assertNull($firstEvent['instructions']);

        $this->assertStringContainsString('thread request', $output);
        $this->assertStringContainsString('thread started', $output);
        $this->assertStringContainsString('tokens used', $output);
    }

    public function test_thread_started_event_uses_codex_provided_initial_input_when_available(): void
    {
        $this->cleanCodexDirectory();

        $service = new TestCodexCliSessionService;
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-123', 'initial_user_input' => 'From Codex'],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 1, 'output_tokens' => 2]],
        ];

        $this->mockExpectation($process, 'setTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'run')
            ->once()
            ->andReturnUsing(function (callable $callback) use ($events): int {
                foreach ($events as $event) {
                    $callback(Process::OUT, json_encode($event)."\n");
                }

                return 0;
            });
        $this->mockExpectation($process, 'isSuccessful')->once()->andReturn(true);
        $this->mockExpectation($process, 'getExitCode')->once()->andReturn(0);

        $service->headlessProcess = $process;

        $result = $service->startSession(['tasks:list'], false, 'local override', 'Follow these');
        $this->assertSame('thread-123', $result['session_id']);

        $expectedDirectory = (string) config('atlas-agent-cli.sessions.path');
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-123.jsonl';
        $this->assertFileExists($expectedPath);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($expectedPath))));
        $threadRequest = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($threadRequest);
        $this->assertSame('thread.request', $threadRequest['type'] ?? null);
        $this->assertSame('Follow these', $threadRequest['instructions'] ?? null);
        $this->assertSame('local override', $threadRequest['task'] ?? null);

        $threadStarted = json_decode($lines[1] ?? '', true);
        $this->assertIsArray($threadStarted);
        $this->assertSame('From Codex', $threadStarted['initial_user_input'] ?? null);
    }

    public function test_thread_request_event_includes_instructions_and_task(): void
    {
        $this->cleanCodexDirectory();

        $service = new TestCodexCliSessionService;
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-123'],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 1, 'output_tokens' => 2]],
        ];

        $this->mockExpectation($process, 'setTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'run')
            ->once()
            ->andReturnUsing(function (callable $callback) use ($events): int {
                foreach ($events as $event) {
                    $callback(Process::OUT, json_encode($event)."\n");
                }

                return 0;
            });
        $this->mockExpectation($process, 'isSuccessful')->once()->andReturn(true);
        $this->mockExpectation($process, 'getExitCode')->once()->andReturn(0);

        $service->headlessProcess = $process;

        $service->startSession(['tasks:list'], false, 'tasks:list --plan', 'Always lint');

        $expectedDirectory = (string) config('atlas-agent-cli.sessions.path');
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-123.jsonl';
        $this->assertFileExists($expectedPath);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($expectedPath))));
        $threadRequest = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($threadRequest);
        $this->assertSame('thread.request', $threadRequest['type'] ?? null);
        $this->assertSame('Always lint', $threadRequest['instructions'] ?? null);
        $this->assertSame('tasks:list --plan', $threadRequest['task'] ?? null);
    }

    public function test_thread_request_event_includes_custom_metadata_only_in_log(): void
    {
        $this->cleanCodexDirectory();

        $service = new TestCodexCliSessionService;
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-456'],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 20]],
        ];

        $this->mockExpectation($process, 'setTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'run')
            ->once()
            ->andReturnUsing(function (callable $callback) use ($events): int {
                foreach ($events as $event) {
                    $callback(Process::OUT, json_encode($event)."\n");
                }

                return 0;
            });
        $this->mockExpectation($process, 'isSuccessful')->once()->andReturn(true);
        $this->mockExpectation($process, 'getExitCode')->once()->andReturn(0);

        $service->headlessProcess = $process;

        $meta = ['assistant_id' => 'assistant-1', 'user_id' => 42];
        $service->startSession(['tasks:list'], false, 'run diagnostics', null, $meta);

        $expectedDirectory = (string) config('atlas-agent-cli.sessions.path');
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-456.jsonl';
        $this->assertFileExists($expectedPath);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($expectedPath))));
        $threadRequest = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($threadRequest);
        $this->assertSame('thread.request', $threadRequest['type'] ?? null);
        $this->assertSame('assistant-1', $threadRequest['assistant_id'] ?? null);
        $this->assertSame(42, $threadRequest['user_id'] ?? null);
        $this->assertSame('run diagnostics', $threadRequest['task'] ?? null);
        $this->assertArrayNotHasKey('assistant_id', json_decode($lines[1] ?? '{}', true) ?: []);
    }

    public function test_headless_session_throws_when_process_fails(): void
    {
        $this->cleanCodexDirectory();

        $service = new TestCodexCliSessionService;
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $this->mockExpectation($process, 'setTimeout')
            ->once()
            ->with(null)
            ->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')
            ->once()
            ->with(null)
            ->andReturnSelf();
        $this->mockExpectation($process, 'run')
            ->once()
            ->andReturn(1);
        $this->mockExpectation($process, 'isSuccessful')
            ->andReturn(false);
        $this->mockExpectation($process, 'getCommandLine')
            ->andReturn('codex exec --json tasks:list');
        $this->mockExpectation($process, 'getExitCode')
            ->andReturn(1);
        $this->mockExpectation($process, 'getExitCodeText')
            ->andReturn('General error');
        $this->mockExpectation($process, 'getWorkingDirectory')
            ->andReturn(getcwd() ?: '');
        $this->mockExpectation($process, 'isOutputDisabled')
            ->andReturn(false);
        $this->mockExpectation($process, 'getOutput')
            ->andReturn('');
        $this->mockExpectation($process, 'getErrorOutput')
            ->andReturn('failure');

        $service->headlessProcess = $process;

        $this->expectException(ProcessFailedException::class);
        $service->startSession(['tasks:list'], false);
    }

    public function test_interactive_session_returns_exit_code_without_json_log(): void
    {
        $service = new TestCodexCliSessionService;
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $this->mockExpectation($process, 'setTimeout')
            ->once()
            ->with(null)
            ->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')
            ->once()
            ->with(null)
            ->andReturnSelf();
        $this->mockExpectation($process, 'setTty')
            ->once()
            ->with(true)
            ->andReturnSelf();
        $this->mockExpectation($process, 'run')
            ->once()
            ->andReturn(0);
        $this->mockExpectation($process, 'isSuccessful')
            ->once()
            ->andReturn(true);
        $this->mockExpectation($process, 'getExitCode')
            ->once()
            ->andReturn(0);

        $service->interactiveProcess = $process;

        $result = $service->startSession(['tasks:list'], true);

        $this->assertTrue(Str::isUuid($result['session_id']));
        $this->assertSame(0, $result['exit_code']);
        $this->assertNull($result['json_file_path']);
    }

    private function cleanCodexDirectory(): void
    {
        $directory = (string) config('atlas-agent-cli.sessions.path');

        if (! is_dir($directory)) {
            return;
        }

        $files = glob($directory.DIRECTORY_SEPARATOR.'*');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
