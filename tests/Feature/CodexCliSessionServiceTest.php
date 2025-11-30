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

        $workspacePath = $this->workspacePath();
        $service = new TestCodexCliSessionService(null, $workspacePath);
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
        $this->mockExpectation($process, 'setWorkingDirectory')
            ->once()
            ->with($workspacePath)
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

        $result = $service->startSession(['tasks:list'], false, 'tasks:list', null, null, null);
        $output = $service->streamedOutput[Process::OUT] ?? '';

        $this->assertSame('thread-123', $result['session_id']);
        $this->assertSame(0, $result['exit_code']);

        $expectedDirectory = $this->codexSessionsDirectory();
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-123.jsonl';
        $this->assertSame($expectedPath, $result['json_file_path']);
        $this->assertFileExists($expectedPath);

        $logContents = file_get_contents($expectedPath);
        $this->assertIsString($logContents);
        $this->assertStringContainsString('thread.started', $logContents);
        $lines = array_values(array_filter(explode("\n", $logContents)));
        $workspaceEvent = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($workspaceEvent);
        $this->assertSame('workspace', $workspaceEvent['type'] ?? null);
        $this->assertSame('codex', $workspaceEvent['provider'] ?? null);
        $this->assertSame(realpath($workspacePath) ?: $workspacePath, $workspaceEvent['workspace_path'] ?? null);
        $this->assertSame($expectedDirectory, $workspaceEvent['session_log_path'] ?? null);
        $this->assertSame('Task: {TASK}', $workspaceEvent['template_task'] ?? null);
        $this->assertSame('Instructions: {INSTRUCTIONS}', $workspaceEvent['template_instructions'] ?? null);
        $this->assertArrayNotHasKey('task_rendered', $workspaceEvent);
        $this->assertArrayNotHasKey('instructions_rendered', $workspaceEvent);
        $this->assertArrayNotHasKey('full_message_rendered', $workspaceEvent);

        $firstEvent = json_decode($lines[1] ?? '', true);
        $this->assertIsArray($firstEvent);
        $this->assertSame('thread.request', $firstEvent['type'] ?? null);
        $this->assertSame('Task: tasks:list', $firstEvent['task'] ?? null);
        $this->assertArrayNotHasKey('instructions', $firstEvent);

        $this->assertStringContainsString('thread request', $output);
        $this->assertStringContainsString('thread started', $output);
        $this->assertStringContainsString('tokens used', $output);
        $this->assertStringContainsString('workspace', $output);
    }

    public function test_workspace_event_logs_workspace_and_model_details(): void
    {
        $this->cleanCodexDirectory();

        $workspacePath = $this->workspacePath();
        $service = new TestCodexCliSessionService(null, $workspacePath);
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-modelled'],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]],
        ];

        $this->mockExpectation($process, 'setTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setWorkingDirectory')->once()->with($workspacePath)->andReturnSelf();
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

        $service->startSession(['--model=o1-mini', '--config=model_reasoning_effort=medium', '--config=approval_policy=never', 'tasks:list'], false, 'tasks:list', null, null, null);

        $expectedDirectory = $this->codexSessionsDirectory();
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-modelled.jsonl';
        $this->assertFileExists($expectedPath);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($expectedPath))));
        $workspaceEvent = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($workspaceEvent);
        $this->assertSame('workspace', $workspaceEvent['type'] ?? null);
        $this->assertSame('o1-mini', $workspaceEvent['model'] ?? null);
        $this->assertSame('medium', $workspaceEvent['reasoning'] ?? null);
        $this->assertSame('never', $workspaceEvent['approval'] ?? null);
        $this->assertSame(realpath($workspacePath) ?: $workspacePath, $workspaceEvent['workspace_path'] ?? null);
    }

    public function test_workspace_event_logs_reasoning_when_split_flag_is_used(): void
    {
        $this->cleanCodexDirectory();

        $workspacePath = $this->workspacePath();
        $service = new TestCodexCliSessionService(null, $workspacePath);
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-reasoning'],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 8, 'output_tokens' => 3]],
        ];

        $this->mockExpectation($process, 'setTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setWorkingDirectory')->once()->with($workspacePath)->andReturnSelf();
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

        $service->startSession(['--config', 'model_reasoning_effort=deep', '--config', 'approval_policy=on-request', 'tasks:list'], false, 'tasks:list', null, null, null);

        $expectedDirectory = $this->codexSessionsDirectory();
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-reasoning.jsonl';
        $this->assertFileExists($expectedPath);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($expectedPath))));
        $workspaceEvent = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($workspaceEvent);
        $this->assertSame('workspace', $workspaceEvent['type'] ?? null);
        $this->assertSame('deep', $workspaceEvent['reasoning'] ?? null);
        $this->assertSame('on-request', $workspaceEvent['approval'] ?? null);
    }

    public function test_workspace_event_logs_approval_policy_when_provided(): void
    {
        $this->cleanCodexDirectory();

        $workspacePath = $this->workspacePath();
        $service = new TestCodexCliSessionService(null, $workspacePath);
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-approval'],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 5, 'output_tokens' => 5]],
        ];

        $this->mockExpectation($process, 'setTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setWorkingDirectory')->once()->with($workspacePath)->andReturnSelf();
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

        $service->startSession(['--config=approval_policy=untrusted', 'tasks:list'], false, 'tasks:list', null, null, null);

        $expectedDirectory = $this->codexSessionsDirectory();
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-approval.jsonl';
        $this->assertFileExists($expectedPath);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($expectedPath))));
        $workspaceEvent = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($workspaceEvent);
        $this->assertSame('workspace', $workspaceEvent['type'] ?? null);
        $this->assertSame('untrusted', $workspaceEvent['approval'] ?? null);
    }

    public function test_workspace_override_changes_process_directory_and_log_entry(): void
    {
        $this->cleanCodexDirectory();

        $baseWorkspacePath = $this->workspacePath();
        $overridePath = $baseWorkspacePath.'/override';
        if (! is_dir($overridePath)) {
            mkdir($overridePath, 0777, true);
        }

        $service = new TestCodexCliSessionService(null, $baseWorkspacePath);
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-override'],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 4]],
        ];

        $this->mockExpectation($process, 'setTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setWorkingDirectory')->once()->with($overridePath)->andReturnSelf();
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

        $service->startSession(['tasks:list'], false, 'tasks:list', null, null, null, $overridePath);

        $expectedDirectory = $this->codexSessionsDirectory();
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-override.jsonl';
        $this->assertFileExists($expectedPath);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($expectedPath))));
        $workspaceEvent = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($workspaceEvent);
        $this->assertSame('workspace', $workspaceEvent['type'] ?? null);
        $this->assertSame(realpath($overridePath) ?: $overridePath, $workspaceEvent['workspace_path'] ?? null);
    }

    public function test_thread_started_event_uses_codex_provided_initial_input_when_available(): void
    {
        $this->cleanCodexDirectory();

        $workspacePath = $this->workspacePath();
        $service = new TestCodexCliSessionService(null, $workspacePath);
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-123', 'initial_user_input' => 'From Codex'],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 1, 'output_tokens' => 2]],
        ];

        $this->mockExpectation($process, 'setTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setWorkingDirectory')->once()->with($workspacePath)->andReturnSelf();
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

        $result = $service->startSession(['tasks:list'], false, 'local override', 'Follow these', null, null);
        $this->assertSame('thread-123', $result['session_id']);

        $expectedDirectory = $this->codexSessionsDirectory();
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-123.jsonl';
        $this->assertFileExists($expectedPath);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($expectedPath))));
        $workspaceEvent = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($workspaceEvent);
        $this->assertSame('workspace', $workspaceEvent['type'] ?? null);

        $threadRequest = json_decode($lines[1] ?? '', true);
        $this->assertIsArray($threadRequest);
        $this->assertSame('thread.request', $threadRequest['type'] ?? null);
        $this->assertSame('Follow these', $threadRequest['instructions'] ?? null);
        $this->assertSame("Instructions: Follow these\nTask: local override", $threadRequest['task'] ?? null);

        $threadStarted = json_decode($lines[2] ?? '', true);
        $this->assertIsArray($threadStarted);
        $this->assertSame('From Codex', $threadStarted['initial_user_input'] ?? null);
    }

    public function test_thread_request_event_includes_instructions_and_task(): void
    {
        $this->cleanCodexDirectory();

        $workspacePath = $this->workspacePath();
        $service = new TestCodexCliSessionService(null, $workspacePath);
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-123'],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 1, 'output_tokens' => 2]],
        ];

        $this->mockExpectation($process, 'setTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setWorkingDirectory')->once()->with($workspacePath)->andReturnSelf();
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

        $service->startSession(['tasks:list'], false, 'tasks:list --plan', 'Always lint', null, null);

        $expectedDirectory = $this->codexSessionsDirectory();
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-123.jsonl';
        $this->assertFileExists($expectedPath);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($expectedPath))));
        $workspaceEvent = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($workspaceEvent);
        $this->assertSame('workspace', $workspaceEvent['type'] ?? null);

        $threadRequest = json_decode($lines[1] ?? '', true);
        $this->assertIsArray($threadRequest);
        $this->assertSame('thread.request', $threadRequest['type'] ?? null);
        $this->assertSame('Always lint', $threadRequest['instructions'] ?? null);
        $this->assertSame("Instructions: Always lint\nTask: tasks:list --plan", $threadRequest['task'] ?? null);
    }

    public function test_task_format_template_is_logged_and_applied(): void
    {
        $this->cleanCodexDirectory();

        config()->set('atlas-agent-cli.template.task', 'Task: {TASK}');
        config()->set('atlas-agent-cli.template.instructions', 'Instructions: {INSTRUCTIONS}');

        $workspacePath = $this->workspacePath();
        $service = new TestCodexCliSessionService(null, $workspacePath);
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-999'],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 5, 'output_tokens' => 10]],
        ];

        $this->mockExpectation($process, 'setTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setWorkingDirectory')->once()->with($workspacePath)->andReturnSelf();
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

        $service->startSession(
            ['tasks:list', '--plan'],
            false,
            'tasks:list --plan',
            'Follow the handbook',
            null,
            null
        );

        $expectedDirectory = $this->codexSessionsDirectory();
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-999.jsonl';
        $this->assertFileExists($expectedPath);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($expectedPath))));

        $workspaceEvent = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($workspaceEvent);
        $this->assertSame('workspace', $workspaceEvent['type'] ?? null);
        $this->assertSame('Task: {TASK}', $workspaceEvent['template_task'] ?? null);
        $this->assertSame('Instructions: {INSTRUCTIONS}', $workspaceEvent['template_instructions'] ?? null);
        $this->assertArrayNotHasKey('task_rendered', $workspaceEvent);
        $this->assertArrayNotHasKey('instructions_rendered', $workspaceEvent);
        $this->assertArrayNotHasKey('full_message_rendered', $workspaceEvent);

        $threadRequest = json_decode($lines[1] ?? '', true);
        $this->assertIsArray($threadRequest);
        $this->assertSame('thread.request', $threadRequest['type'] ?? null);
        $this->assertSame(
            "Instructions: Follow the handbook\nTask: tasks:list --plan",
            $threadRequest['task'] ?? null
        );
        $this->assertSame('Task: {TASK}', $threadRequest['template_task'] ?? null);
        $this->assertSame('Instructions: {INSTRUCTIONS}', $threadRequest['template_instructions'] ?? null);
        $this->assertSame('Task: tasks:list --plan', $threadRequest['task_rendered'] ?? null);
        $this->assertSame('Instructions: Follow the handbook', $threadRequest['instructions_rendered'] ?? null);
        $this->assertSame(
            "Instructions: Follow the handbook\nTask: tasks:list --plan",
            $threadRequest['full_message_rendered'] ?? null
        );
        $this->assertSame('Follow the handbook', $threadRequest['instructions'] ?? null);
    }

    public function test_task_format_template_passed_to_service_overrides_config(): void
    {
        $this->cleanCodexDirectory();

        config()->set('atlas-agent-cli.template.task', 'Config Task: {TASK}');
        config()->set('atlas-agent-cli.template.instructions', 'Config Instructions: {INSTRUCTIONS}');

        $workspacePath = $this->workspacePath();
        $service = new TestCodexCliSessionService(null, $workspacePath);
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-custom-template'],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 5, 'output_tokens' => 10]],
        ];

        $this->mockExpectation($process, 'setTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setWorkingDirectory')->once()->with($workspacePath)->andReturnSelf();
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

        $service->startSession(
            ['tasks:list'],
            false,
            'review changes',
            'Apply the template override',
            null,
            null,
            null,
            [
                'task' => 'Custom Task: {TASK}',
                'instructions' => 'Custom Instructions: {INSTRUCTIONS}',
            ]
        );

        $expectedDirectory = $this->codexSessionsDirectory();
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-custom-template.jsonl';
        $this->assertFileExists($expectedPath);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($expectedPath))));

        $workspaceEvent = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($workspaceEvent);
        $this->assertSame('workspace', $workspaceEvent['type'] ?? null);
        $this->assertSame('Custom Task: {TASK}', $workspaceEvent['template_task'] ?? null);
        $this->assertSame('Custom Instructions: {INSTRUCTIONS}', $workspaceEvent['template_instructions'] ?? null);
        $this->assertArrayNotHasKey('task_rendered', $workspaceEvent);
        $this->assertArrayNotHasKey('instructions_rendered', $workspaceEvent);
        $this->assertArrayNotHasKey('full_message_rendered', $workspaceEvent);

        $threadRequest = json_decode($lines[1] ?? '', true);
        $this->assertIsArray($threadRequest);
        $this->assertSame('thread.request', $threadRequest['type'] ?? null);
        $this->assertSame(
            "Custom Instructions: Apply the template override\nCustom Task: review changes",
            $threadRequest['task'] ?? null
        );
        $this->assertSame('Custom Task: {TASK}', $threadRequest['template_task'] ?? null);
        $this->assertSame('Custom Instructions: {INSTRUCTIONS}', $threadRequest['template_instructions'] ?? null);
        $this->assertSame('Custom Task: review changes', $threadRequest['task_rendered'] ?? null);
        $this->assertSame(
            'Custom Instructions: Apply the template override',
            $threadRequest['instructions_rendered'] ?? null
        );
        $this->assertSame(
            "Custom Instructions: Apply the template override\nCustom Task: review changes",
            $threadRequest['full_message_rendered'] ?? null
        );
    }

    public function test_thread_request_event_includes_custom_metadata_only_in_log(): void
    {
        $this->cleanCodexDirectory();

        $workspacePath = $this->workspacePath();
        $service = new TestCodexCliSessionService(null, $workspacePath);
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'thread.started', 'thread_id' => 'thread-456'],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 10, 'output_tokens' => 20]],
        ];

        $this->mockExpectation($process, 'setTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setWorkingDirectory')->once()->with($workspacePath)->andReturnSelf();
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
        $service->startSession(['tasks:list'], false, 'run diagnostics', null, $meta, null);

        $expectedDirectory = $this->codexSessionsDirectory();
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-456.jsonl';
        $this->assertFileExists($expectedPath);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($expectedPath))));
        $workspaceEvent = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($workspaceEvent);
        $this->assertSame('workspace', $workspaceEvent['type'] ?? null);

        $threadRequest = json_decode($lines[1] ?? '', true);
        $this->assertIsArray($threadRequest);
        $this->assertSame('thread.request', $threadRequest['type'] ?? null);
        $this->assertSame('assistant-1', $threadRequest['assistant_id'] ?? null);
        $this->assertSame(42, $threadRequest['user_id'] ?? null);
        $this->assertSame('Task: run diagnostics', $threadRequest['task'] ?? null);
        $this->assertArrayNotHasKey('assistant_id', json_decode($lines[2] ?? '{}', true) ?: []);
    }

    public function test_thread_resumed_event_is_logged_when_resuming_existing_session(): void
    {
        $this->cleanCodexDirectory();

        $workspacePath = $this->workspacePath();
        $service = new TestCodexCliSessionService(null, $workspacePath);
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class);

        $events = [
            ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'acknowledged']],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 50, 'output_tokens' => 75]],
        ];

        $this->mockExpectation($process, 'setTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setIdleTimeout')->once()->with(null)->andReturnSelf();
        $this->mockExpectation($process, 'setWorkingDirectory')->once()->with($workspacePath)->andReturnSelf();
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

        $service->startSession(
            ['tasks:resume'],
            false,
            'continue troubleshooting',
            null,
            null,
            'thread-789'
        );

        $expectedDirectory = $this->codexSessionsDirectory();
        $expectedPath = $expectedDirectory.DIRECTORY_SEPARATOR.'thread-789.jsonl';
        $this->assertFileExists($expectedPath);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($expectedPath))));
        $workspaceEvent = json_decode($lines[0] ?? '', true);
        $this->assertIsArray($workspaceEvent);
        $this->assertSame('workspace', $workspaceEvent['type'] ?? null);

        $firstEvent = json_decode($lines[1] ?? '', true);
        $this->assertIsArray($firstEvent);
        $this->assertSame('thread.resumed', $firstEvent['type'] ?? null);
        $this->assertSame('Task: continue troubleshooting', $firstEvent['task'] ?? null);
        $this->assertSame('Task: {TASK}', $firstEvent['template_task'] ?? null);
        $this->assertSame('Instructions: {INSTRUCTIONS}', $firstEvent['template_instructions'] ?? null);
        $this->assertSame('Task: continue troubleshooting', $firstEvent['task_rendered'] ?? null);
        $this->assertArrayNotHasKey('instructions_rendered', $firstEvent);
        $this->assertSame('Task: continue troubleshooting', $firstEvent['full_message_rendered'] ?? null);
        $this->assertArrayNotHasKey('instructions', $firstEvent);
    }

    public function test_headless_session_throws_when_process_fails(): void
    {
        $this->cleanCodexDirectory();

        $workspacePath = $this->workspacePath();
        $service = new TestCodexCliSessionService(null, $workspacePath);
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
        $this->mockExpectation($process, 'setWorkingDirectory')
            ->once()
            ->with($workspacePath)
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
        $workspacePath = $this->workspacePath();
        $service = new TestCodexCliSessionService(null, $workspacePath);
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
        $this->mockExpectation($process, 'setWorkingDirectory')
            ->once()
            ->with($workspacePath)
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
        $directory = $this->codexSessionsDirectory();

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
