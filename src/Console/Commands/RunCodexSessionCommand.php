<?php

declare(strict_types=1);

namespace Atlas\Agent\Console\Commands;

use Atlas\Agent\Services\CodexCliSessionService;
use Illuminate\Console\Command;

/**
 * Class RunCodexSessionCommand
 *
 * Runs the Codex CLI with streaming output, sanitised logging, and JSONL persistence.
 */
class RunCodexSessionCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'codex:session {args* : Arguments to pass to the Codex CLI} {--interactive : Run Codex attached to your terminal without logging}';

    /**
     * @var string
     */
    protected $description = 'Run Codex CLI, stream output, and record it to a per-session JSON log file';

    private CodexCliSessionService $sessionService;

    public function __construct(CodexCliSessionService $sessionService)
    {
        parent::__construct();
        $this->sessionService = $sessionService;
    }

    public function handle(): int
    {
        $arguments = $this->argument('args') ?? [];

        if (count($arguments) === 0) {
            $this->error('You must provide arguments for the Codex CLI.');
            return self::FAILURE;
        }

        $interactive = (bool) $this->option('interactive');

        try {
            $result = $this->sessionService->startSession($arguments, $interactive);
        } catch (\Throwable $exception) {
            $this->error('Codex session failed: ' . $exception->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Codex session completed.');
        $this->line('Session ID: ' . $result['session_id']);
        $this->line('JSON log file: ' . ($result['json_file_path'] ?? 'N/A (interactive)'));
        $this->line('Exit code: ' . $result['exit_code']);

        return $result['exit_code'] ?? self::SUCCESS;
    }
}
