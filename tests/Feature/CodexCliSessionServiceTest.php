<?php

declare(strict_types=1);

namespace Atlas\Agent\Tests\Feature;

use Atlas\Agent\Tests\Support\TestCodexCliSessionService;
use Atlas\Agent\Tests\TestCase;
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
    public function testHeadlessSessionStreamsOutputAndStoresLog(): void
    {
        $this->cleanCodexDirectory();

        $service = new TestCodexCliSessionService();
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-123'],
            ['type' => 'item.started', 'item' => ['type' => 'command_execution', 'command' => 'ls -la', 'id' => 'item-1']],
            ['type' => 'item.completed', 'item' => ['type' => 'command_execution', 'id' => 'item-1', 'aggregated_output' => "output\r\nline", 'exit_code' => 0]],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 1200, 'output_tokens' => 345]],
        ];

        $process->shouldReceive('setTimeout')->once()->with(null)->andReturnNull();
        $process->shouldReceive('setIdleTimeout')->once()->with(null)->andReturnNull();
        $process->shouldReceive('run')->once()->andReturnUsing(function (callable $callback) use ($events): void {
            foreach ($events as $event) {
                $callback(Process::OUT, json_encode($event) . "\n");
            }
        });
        $process->shouldReceive('isSuccessful')->once()->andReturn(true);
        $process->shouldReceive('getExitCode')->once()->andReturn(0);

        $service->headlessProcess = $process;

        ob_start();
        $result = $service->startSession(['tasks:list'], false);
        $output = ob_get_clean() ?: '';

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

    public function testHeadlessSessionThrowsWhenProcessFails(): void
    {
        $this->cleanCodexDirectory();

        $service = new TestCodexCliSessionService();
        $process = Mockery::mock(Process::class);

        $process->shouldReceive('setTimeout')->once()->with(null)->andReturnNull();
        $process->shouldReceive('setIdleTimeout')->once()->with(null)->andReturnNull();
        $process->shouldReceive('run')->once()->andReturnNull();
        $process->shouldReceive('isSuccessful')->once()->andReturn(false);
        $process->shouldReceive('getCommandLine')->andReturn('codex exec --json tasks:list');
        $process->shouldReceive('getExitCode')->andReturn(1);
        $process->shouldReceive('getExitCodeText')->andReturn('General error');
        $process->shouldReceive('getWorkingDirectory')->andReturn(getcwd() ?: '');
        $process->shouldReceive('isOutputDisabled')->andReturn(false);
        $process->shouldReceive('getOutput')->andReturn('');
        $process->shouldReceive('getErrorOutput')->andReturn('failure');

        $service->headlessProcess = $process;

        $this->expectException(ProcessFailedException::class);
        $service->startSession(['tasks:list'], false);
    }

    private function cleanCodexDirectory(): void
    {
        $directory = storage_path('app/codex_sessions');

        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*');
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
