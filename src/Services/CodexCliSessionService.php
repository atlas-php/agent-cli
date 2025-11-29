<?php

declare(strict_types=1);

namespace Atlas\Agent\Services;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Class CodexCliSessionService
 *
 * Runs Codex CLI sessions either interactively (attached to the user's terminal)
 * or headless with streamed output and sanitized JSON logging.
 */
class CodexCliSessionService
{
    private string $sessionDirectory;

    public function __construct(?string $sessionDirectory = null)
    {
        $this->sessionDirectory = $sessionDirectory ?? storage_path('app/codex_sessions');
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array{session_id: string, json_file_path: string|null, exit_code: int|null}
     */
    public function startSession(
        array $arguments,
        bool $interactive = false,
        ?string $initialUserInput = null,
        ?string $systemInstructions = null
    ): array
    {
        $sessionId = (string) Str::uuid();

        if ($interactive) {
            $process = $this->runInteractive($arguments);

            return [
                'session_id' => $sessionId,
                'json_file_path' => null,
                'exit_code' => $process->getExitCode(),
            ];
        }

        $jsonFilePath = $this->prepareJsonLogFile($sessionId);
        $headlessResult = $this->runHeadless($arguments, $jsonFilePath, $initialUserInput, $systemInstructions);
        $process = $headlessResult['process'];
        $codexSessionId = $headlessResult['codex_session_id'] ?? $sessionId;

        if ($headlessResult['codex_session_id'] !== null) {
            $desiredJsonPath = $this->prepareJsonLogFile($codexSessionId);
            if ($jsonFilePath !== $desiredJsonPath) {
                if (@rename($jsonFilePath, $desiredJsonPath)) {
                    $jsonFilePath = $desiredJsonPath;
                }
            }
        }

        return [
            'session_id' => $codexSessionId,
            'json_file_path' => $jsonFilePath,
            'exit_code' => $process->getExitCode(),
        ];
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function runInteractive(array $arguments): Process
    {
        $process = $this->buildProcess($arguments, true);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        try {
            $process->setTty(true);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Interactive Codex sessions require a TTY-enabled terminal.', 0, $exception);
        }

        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array{process: Process, codex_session_id: string|null}
     */
    private function runHeadless(
        array $arguments,
        string $jsonFilePath,
        ?string $initialUserInput,
        ?string $systemInstructions
    ): array
    {
        $process = $this->buildProcess($arguments, false);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        $jsonHandle = fopen($jsonFilePath, 'ab');

        if ($jsonHandle === false) {
            throw new RuntimeException('Unable to open JSON log file for writing: '.$jsonFilePath);
        }

        $jsonBuffer = '';
        $codexSessionId = null;
        /**
         * @var array{
         *     pending_items: array<string, string>,
         *     initial_user_input: string|null,
         *     thread_input_applied: bool,
         *     system_instructions: string|null,
         *     thread_request_logged: bool
         * } $jsonState
         */
        $jsonState = [
            'pending_items' => [],
            'initial_user_input' => $initialUserInput !== null ? trim($initialUserInput) : null,
            'thread_input_applied' => false,
            'system_instructions' => $systemInstructions !== null ? trim($systemInstructions) : null,
            'thread_request_logged' => false,
        ];

        $this->maybeLogThreadRequestEvent($jsonHandle, $codexSessionId, $jsonState);

        try {
            $process->run(function (string $type, string $buffer) use ($jsonHandle, &$jsonBuffer, &$codexSessionId, &$jsonState) {
                if ($type === Process::OUT) {
                    $jsonBuffer .= $buffer;
                    $this->processJsonBuffer($jsonBuffer, $jsonHandle, $codexSessionId, $jsonState);

                    return;
                }

                $clean = $this->stripEscapeSequences($buffer);
                if ($clean !== '') {
                    $this->streamToTerminal(Process::ERR, $clean);
                }
            });

            if (trim($jsonBuffer) !== '') {
                $this->processJsonLine($jsonBuffer, $jsonHandle, $codexSessionId, $jsonState);
            }
        } finally {
            fclose($jsonHandle);
        }

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return [
            'process' => $process,
            'codex_session_id' => $codexSessionId,
        ];
    }

    /**
     * @param  array<int, string>  $arguments
     */
    protected function buildProcess(array $arguments, bool $interactive): Process
    {
        $command = $interactive
            ? array_merge(['codex'], $arguments)
            : array_merge(['codex', 'exec', '--json'], $arguments);

        return new Process($command);
    }

    private function prepareJsonLogFile(string $sessionId): string
    {
        $directory = $this->sessionDirectory;

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory.DIRECTORY_SEPARATOR.$sessionId.'.jsonl';
    }

    private function stripEscapeSequences(string $text): string
    {
        $patterns = [
            '/\x1B\[[0-9;?]*[ -\\/]*[@-~]/',
            '/\x1B\][^\x07\x1B]*(\x07|\x1B\\\\)/',
            '/\x1B[@-Z\\\\-_]/',
            '/\x0F/',
            '/\x0E/',
        ];

        $stripped = preg_replace($patterns, '', $text) ?? '';
        $stripped = preg_replace("/\r\n/", "\n", $stripped) ?? '';
        $stripped = str_replace("\r", '', $stripped);

        return $stripped;
    }

    /**
     * @param  resource  $jsonHandle
     * @param  array{
     *     pending_items: array<string, string>,
     *     initial_user_input: string|null,
     *     thread_input_applied: bool,
     *     system_instructions: string|null,
     *     thread_request_logged: bool
     * }  $state
     */
    private function processJsonBuffer(string &$buffer, $jsonHandle, ?string &$codexSessionId, array &$state): void
    {
        while (($newlinePosition = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $newlinePosition);
            $buffer = substr($buffer, $newlinePosition + 1);
            $this->processJsonLine($line, $jsonHandle, $codexSessionId, $state);
        }
    }

    /**
     * @param  resource  $jsonHandle
     * @param  array{
     *     pending_items: array<string, string>,
     *     initial_user_input: string|null,
     *     thread_input_applied: bool,
     *     system_instructions: string|null,
     *     thread_request_logged: bool
     * }  $state
     */
    private function processJsonLine(string $line, $jsonHandle, ?string &$codexSessionId, array &$state): void
    {
        $rawLine = rtrim($line, "\r\n");
        $trimmed = trim($rawLine);
        if ($trimmed === '') {
            return;
        }

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded)) {
            fwrite($jsonHandle, $rawLine."\n");
            $this->streamToTerminal(Process::OUT, $trimmed."\n");

            return;
        }

        $this->maybeCaptureUserInputFromEvent($decoded, $state);
        $updatedEvent = $this->maybeAugmentThreadStartedEvent($decoded, $state);

        $encodedLine = $rawLine;
        if ($updatedEvent !== $decoded) {
            $reEncoded = $this->encodeEventLine($updatedEvent);
            if ($reEncoded !== null) {
                $encodedLine = $reEncoded;
                $decoded = $updatedEvent;
            }
        }

        fwrite($jsonHandle, $encodedLine."\n");

        $rendered = $this->renderCodexEvent($decoded, $codexSessionId, $state);

        if ($rendered !== null && $rendered !== '') {
            $this->streamToTerminal(Process::OUT, $rendered);
        }
    }

    /**
     * @param  resource  $jsonHandle
     * @param  array{
     *     pending_items: array<string, string>,
     *     initial_user_input: string|null,
     *     thread_input_applied: bool,
     *     system_instructions: string|null,
     *     thread_request_logged: bool
     * }  $state
     */
    private function maybeLogThreadRequestEvent($jsonHandle, ?string &$codexSessionId, array &$state): void
    {
        if ($state['thread_request_logged'] === true) {
            return;
        }

        $instructions = isset($state['system_instructions']) ? trim((string) $state['system_instructions']) : '';
        if ($instructions === '') {
            $instructions = null;
        }

        $task = isset($state['initial_user_input']) ? trim((string) $state['initial_user_input']) : '';
        if ($task === '') {
            $task = null;
        }

        if ($instructions === null && $task === null) {
            return;
        }

        $event = [
            'type' => 'thread.request',
            'instructions' => $instructions,
            'task' => $task,
        ];

        $encoded = $this->encodeEventLine($event);
        if ($encoded === null) {
            return;
        }

        $this->processJsonLine($encoded, $jsonHandle, $codexSessionId, $state);
        $state['thread_request_logged'] = true;
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array{
     *     pending_items: array<string, string>,
     *     initial_user_input: string|null,
     *     thread_input_applied: bool,
     *     system_instructions: string|null,
     *     thread_request_logged: bool
     * }  $state
     */
    private function maybeCaptureUserInputFromEvent(array $event, array &$state): void
    {
        if (($event['type'] ?? '') === 'thread.request') {
            $instructions = trim((string) ($event['instructions'] ?? ''));
            if ($instructions !== '') {
                $state['system_instructions'] = $instructions;
            }

            $task = trim((string) ($event['task'] ?? ''));
            if ($task !== '') {
                $state['initial_user_input'] = $task;
            }

            $state['thread_request_logged'] = true;

            return;
        }

        if (isset($state['initial_user_input']) && trim((string) $state['initial_user_input']) !== '') {
            return;
        }

        if (($event['type'] ?? '') === 'thread.started') {
            $threadInput = trim((string) ($event['initial_user_input'] ?? ''));
            if ($threadInput !== '') {
                $state['initial_user_input'] = $threadInput;
            }

            return;
        }

        $item = $event['item'] ?? null;
        if (! is_array($item)) {
            return;
        }

        $role = isset($item['role']) ? (string) $item['role'] : '';
        $type = isset($item['type']) ? (string) $item['type'] : '';

        $looksLikeUser = $role === 'user' || ($type !== '' && str_contains($type, 'user'));
        if (! $looksLikeUser) {
            return;
        }

        $text = $this->extractItemText($item);
        if ($text !== null && $text !== '') {
            $state['initial_user_input'] = $text;
        }
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array{
     *     pending_items: array<string, string>,
     *     initial_user_input: string|null,
     *     thread_input_applied: bool,
     *     system_instructions: string|null,
     *     thread_request_logged: bool
     * }  $state
     * @return array<string, mixed>
     */
    private function maybeAugmentThreadStartedEvent(array $event, array &$state): array
    {
        if (($event['type'] ?? '') !== 'thread.started') {
            return $event;
        }

        $existing = trim((string) ($event['initial_user_input'] ?? ''));
        if ($existing !== '') {
            $state['thread_input_applied'] = true;

            return $event;
        }

        $candidate = trim((string) ($state['initial_user_input'] ?? ''));
        if ($candidate === '') {
            return $event;
        }

        $event['initial_user_input'] = $candidate;
        $state['thread_input_applied'] = true;

        return $event;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function extractItemText(array $item): ?string
    {
        $text = isset($item['text']) ? trim((string) $item['text']) : '';
        if ($text !== '') {
            return $text;
        }

        $content = $item['content'] ?? null;
        if (! is_array($content)) {
            return null;
        }

        $parts = [];
        foreach ($content as $contentPart) {
            if (! is_array($contentPart)) {
                continue;
            }

            $payload = trim((string) ($contentPart['text'] ?? ''));
            if ($payload === '') {
                continue;
            }

            $contentType = (string) ($contentPart['type'] ?? '');
            if ($contentType === '' || $contentType === 'text' || $contentType === 'input_text') {
                $parts[] = $payload;
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode("\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function encodeEventLine(array $event): ?string
    {
        $encoded = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : null;
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array{
     *     pending_items: array<string, string>,
     *     initial_user_input: string|null,
     *     thread_input_applied: bool,
     *     system_instructions: string|null,
     *     thread_request_logged: bool
     * }  $state
     */
    private function renderCodexEvent(array $event, ?string &$codexSessionId, array &$state): ?string
    {
        $type = (string) ($event['type'] ?? '');

        switch ($type) {
            case 'thread.request':
                $instructions = trim((string) ($event['instructions'] ?? ''));
                $task = trim((string) ($event['task'] ?? ''));

                return $this->formatLines([
                    'thread request',
                    $instructions !== '' ? 'instructions: '.$instructions : null,
                    $task !== '' ? 'task: '.$task : null,
                ]);

            case 'thread.started':
                if (isset($event['thread_id']) && $event['thread_id'] !== '') {
                    $codexSessionId = (string) $event['thread_id'];
                }

                return $this->formatLines([
                    'thread started',
                    $codexSessionId !== null ? 'session id: '.$codexSessionId : null,
                ]);

            case 'turn.started':
                return null;

            case 'item.started':
                return $this->renderItemEvent('started', $event['item'] ?? [], $state);

            case 'item.updated':
                return $this->renderItemEvent('updated', $event['item'] ?? [], $state);

            case 'item.completed':
                return $this->renderItemEvent('completed', $event['item'] ?? [], $state);

            case 'turn.completed':
                return $this->renderUsageSummary($event['usage'] ?? []);
        }

        return $this->formatLines([
            'event: '.$type,
            json_encode($event, JSON_PRETTY_PRINT) ?: '{}',
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{
     *     pending_items: array<string, string>,
     *     initial_user_input: string|null,
     *     thread_input_applied: bool,
     *     system_instructions: string|null,
     *     thread_request_logged: bool
     * }  $state
     */
    private function renderItemEvent(string $phase, array $item, array &$state): ?string
    {
        $itemType = (string) ($item['type'] ?? '');

        return match ($itemType) {
            'reasoning' => $this->renderReasoningItem($item),
            'agent_message' => $this->renderAgentMessageItem($item),
            'command_execution' => $this->renderCommandExecutionItem($phase, $item, $state),
            default => $this->formatLines([
                sprintf('item (%s) %s', $itemType !== '' ? $itemType : 'unknown', $phase),
                json_encode($item, JSON_PRETTY_PRINT) ?: '{}',
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function renderReasoningItem(array $item): ?string
    {
        $text = trim((string) ($item['text'] ?? ''));

        if ($text === '') {
            return null;
        }

        return $this->formatLines([
            'thinking',
            $text,
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function renderAgentMessageItem(array $item): ?string
    {
        $text = trim((string) ($item['text'] ?? ''));

        if ($text === '') {
            return null;
        }

        return $this->formatLines([
            'codex',
            $text,
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{
     *     pending_items: array<string, string>,
     *     initial_user_input: string|null,
     *     thread_input_applied: bool,
     *     system_instructions: string|null,
     *     thread_request_logged: bool
     * }  $state
     */
    private function renderCommandExecutionItem(string $phase, array $item, array &$state): ?string
    {
        $command = (string) ($item['command'] ?? '');
        $itemId = (string) ($item['id'] ?? '');

        $hasPending = $itemId !== '' && array_key_exists($itemId, $state['pending_items']);

        if ($phase === 'started') {
            if ($itemId !== '') {
                $state['pending_items'][$itemId] = $command;
            }

            return $this->formatLines([
                'exec',
                $command,
            ]);
        }

        if ($command === '' && $hasPending) {
            $command = (string) $state['pending_items'][$itemId];
        }

        if ($phase === 'completed' && $hasPending) {
            unset($state['pending_items'][$itemId]);
        }

        $output = $this->stripEscapeSequences((string) ($item['aggregated_output'] ?? ''));
        $exitCode = $item['exit_code'] ?? null;

        $lines = [];

        if ($phase === 'completed' && ! $hasPending && $command !== '') {
            $lines[] = 'exec';
            $lines[] = $command;
        }

        if ($output !== '') {
            $lines[] = rtrim($output, "\n");
        }

        if ($exitCode !== null && $phase === 'completed') {
            $lines[] = 'exit code: '.$exitCode;
        }

        return $this->formatLines($lines);
    }

    /**
     * @param  array<string, int>  $usage
     */
    private function renderUsageSummary(array $usage): ?string
    {
        if ($usage === []) {
            return null;
        }

        $input = $usage['input_tokens'] ?? null;
        $cached = $usage['cached_input_tokens'] ?? null;
        $output = $usage['output_tokens'] ?? null;

        $lines = ['tokens used'];

        if ($input !== null) {
            $line = 'input: '.number_format((int) $input);
            if ($cached !== null) {
                $line .= ' (cached: '.number_format((int) $cached).')';
            }
            $lines[] = $line;
        }

        if ($output !== null) {
            $lines[] = 'output: '.number_format((int) $output);
        }

        return $this->formatLines($lines);
    }

    /**
     * @param  array<int, string|null>  $lines
     */
    private function formatLines(array $lines): ?string
    {
        $filtered = array_values(array_filter($lines, static function ($line) {
            return $line !== null && $line !== '';
        }));

        if ($filtered === []) {
            return null;
        }

        return implode("\n", $filtered)."\n";
    }

    protected function streamToTerminal(string $type, string $cleanBuffer): void
    {
        $stream = $type === Process::OUT ? STDOUT : STDERR;

        fwrite($stream, $cleanBuffer);
        fflush($stream);
    }
}
