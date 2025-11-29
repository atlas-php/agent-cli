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

        $result = $service->startSession(['tasks:list'], false);
        $output = $service->streamedOutput[Process::OUT] ?? '';

        $this->assertSame('thread-123', $result['session_id']);
        $this->assertSame(0, $result['exit_code']);

        $expectedPath = storage_path('app/codex_sessions/thread-123.jsonl');
        $this->assertSame($expectedPath, $result['json_file_path']);
        $this->assertFileExists($expectedPath);

        $logContents = file_get_contents($expectedPath);
        $this->assertIsString($logContents);
        $this->assertStringContainsString('thread.started', $logContents);

        $this->assertStringContainsString('thread started', $output);
        $this->assertStringContainsString('tokens used', $output);
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
        $directory = storage_path('app/codex_sessions');

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
