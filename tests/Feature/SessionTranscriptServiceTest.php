<?php

declare(strict_types=1);

namespace Atlas\Agent\Tests\Feature;

use Atlas\Agent\Services\SessionTranscripts\SessionTranscriptService;
use Atlas\Agent\Tests\TestCase;
use InvalidArgumentException;

/**
 * Class SessionTranscriptServiceTest
 *
 * Validates transcript parsing into events, todos, and turn actions.
 */
final class SessionTranscriptServiceTest extends TestCase
{
    public function test_full_transcript_returns_decoded_events(): void
    {
        $sessionId = 'thread-session-history';
        $events = $this->seedCodexSession($sessionId);
        $service = app(SessionTranscriptService::class);

        $transcript = $service->fullTranscript('codex', $sessionId);

        $this->assertSame($events, $transcript);
    }

    public function test_todo_list_tracks_status_progression(): void
    {
        $sessionId = 'thread-session-todos';
        $this->seedCodexSession($sessionId);
        $service = app(SessionTranscriptService::class);

        $todos = $service->todoList('codex', $sessionId);

        $this->assertCount(2, $todos);
        $this->assertSame('todo-1', $todos[0]['id']);
        $this->assertSame('completed', $todos[0]['status']);
        $this->assertSame('todo-2', $todos[1]['id']);
        $this->assertSame('in_progress', $todos[1]['status']);
        $this->assertSame('Write tests for parser', $todos[1]['title']);
    }

    public function test_turns_group_actions_and_usage(): void
    {
        $sessionId = 'thread-session-turns';
        $this->seedCodexSession($sessionId);
        $service = app(SessionTranscriptService::class);

        $turns = $service->turns('codex', $sessionId);

        $this->assertCount(2, $turns);
        $this->assertSame(1, $turns[0]['index']);
        $this->assertCount(4, $turns[0]['actions']);
        $this->assertSame(['input_tokens' => 12, 'cached_input_tokens' => 2, 'output_tokens' => 4], $turns[0]['usage']);
        $this->assertSame('item.started', $turns[0]['actions'][0]['type']);
        $this->assertSame('todo', $turns[0]['actions'][0]['item_type']);

        $this->assertSame(2, $turns[1]['index']);
        $this->assertCount(2, $turns[1]['actions']);
        $this->assertSame(['input_tokens' => 15, 'cached_input_tokens' => 0, 'output_tokens' => 7], $turns[1]['usage']);
        $this->assertSame('item.started', $turns[1]['actions'][0]['type']);
        $this->assertSame('todo', $turns[1]['actions'][0]['item_type']);
    }

    public function test_usage_totals_aggregate_all_turns(): void
    {
        $sessionId = 'thread-session-usage';
        $this->seedCodexSession($sessionId);
        $service = app(SessionTranscriptService::class);

        $totals = $service->usageTotals('codex', $sessionId);

        $this->assertSame([
            'input_tokens' => 27,
            'cached_input_tokens' => 2,
            'output_tokens' => 11,
            'total_tokens' => 38,
        ], $totals);
    }

    public function test_unsupported_provider_throws(): void
    {
        $service = app(SessionTranscriptService::class);

        $this->expectException(InvalidArgumentException::class);
        $service->fullTranscript('unknown-provider', 'abc');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function seedCodexSession(string $sessionId): array
    {
        $events = [
            ['type' => 'workspace', 'provider' => 'codex'],
            ['type' => 'thread.started', 'thread_id' => $sessionId],
            ['type' => 'turn.started'],
            ['type' => 'item.started', 'item' => ['type' => 'todo', 'id' => 'todo-1', 'title' => 'Install dependencies']],
            ['type' => 'item.completed', 'item' => ['type' => 'todo', 'id' => 'todo-1', 'title' => 'Install dependencies', 'status' => 'done']],
            ['type' => 'item.started', 'item' => ['type' => 'command_execution', 'id' => 'cmd-1', 'command' => 'composer lint']],
            ['type' => 'item.completed', 'item' => ['type' => 'command_execution', 'id' => 'cmd-1', 'command' => 'composer lint', 'exit_code' => 0]],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 12, 'cached_input_tokens' => 2, 'output_tokens' => 4]],
            ['type' => 'turn.started'],
            ['type' => 'item.started', 'item' => ['type' => 'todo', 'id' => 'todo-2', 'title' => 'Write tests for parser', 'status' => 'in_progress']],
            ['type' => 'item.updated', 'item' => ['type' => 'todo', 'id' => 'todo-2', 'title' => 'Write tests for parser', 'status' => 'in processing']],
            ['type' => 'turn.completed', 'usage' => ['input_tokens' => 15, 'output_tokens' => 7]],
        ];

        $directory = $this->codexSessionsDirectory();
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $lines = [];
        foreach ($events as $event) {
            $encoded = json_encode($event, JSON_UNESCAPED_SLASHES);
            $this->assertIsString($encoded);
            $lines[] = $encoded;
        }

        $path = $directory.DIRECTORY_SEPARATOR.$sessionId.'.jsonl';
        file_put_contents($path, implode("\n", $lines));

        return $events;
    }
}
