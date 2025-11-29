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
    private const PROVIDER = 'codex';

    /**
     * @var string
     */
    protected $signature = 'codex:session
        {args?* : Arguments to pass to the Codex CLI}
        {--interactive : Run Codex attached to your terminal without logging}
        {--model= : Override the Codex model for this run}
        {--instructions= : Additional system instructions appended ahead of the user task}
        {--meta= : JSON object of metadata stored on the synthetic thread lifecycle log entry}
        {--resume= : Resume an existing Codex thread by identifier}
        {--workspace= : Absolute path Codex should treat as its working directory for this run}
    ';

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
        /** @var mixed $rawArguments */
        $rawArguments = $this->argument('args');
        $arguments = [];

        if (is_array($rawArguments)) {
            foreach ($rawArguments as $argument) {
                if ($argument === null) {
                    continue;
                }
                $arguments[] = (string) $argument;
            }
        } elseif (is_string($rawArguments) && $rawArguments !== '') {
            $arguments[] = (string) $rawArguments;
        }

        if (count($arguments) === 0) {
            $this->error('You must provide arguments for the Codex CLI.');

            return self::FAILURE;
        }

        $workspaceOverride = $this->resolveWorkspaceOption();
        $initialUserInput = $this->buildInitialUserInput($arguments);
        $systemInstructions = $this->resolveInstructionsOption();

        try {
            $threadRequestMeta = $this->resolveMetaOption();
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $resumeThreadId = $this->resolveResumeOption();
        $arguments = $this->injectRuntimeOptions($arguments);
        $interactive = (bool) $this->option('interactive');

        try {
            $result = $this->sessionService->startSession(
                $arguments,
                $interactive,
                $initialUserInput,
                $systemInstructions,
                $threadRequestMeta,
                $resumeThreadId,
                $workspaceOverride
            );
        } catch (\Throwable $exception) {
            $this->error('Codex session failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Codex session completed.');
        $this->line('Session ID: '.$result['session_id']);
        $this->line('JSON log file: '.($result['json_file_path'] ?? 'N/A (interactive)'));
        $this->line('Exit code: '.$result['exit_code']);

        return $result['exit_code'] ?? self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    private function injectRuntimeOptions(array $arguments): array
    {
        $injected = [];

        $model = $this->resolveModelOption();
        if ($model !== null && $this->shouldInjectFlag($arguments, '--model')) {
            $injected[] = '--model='.$model;
        }

        if ($injected === []) {
            return $arguments;
        }

        return array_merge($injected, $arguments);
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function buildInitialUserInput(array $arguments): ?string
    {
        $filtered = array_values(array_filter($arguments, static function (string $argument): bool {
            return trim($argument) !== '';
        }));

        if ($filtered === []) {
            return null;
        }

        return implode(' ', $filtered);
    }

    private function resolveModelOption(): ?string
    {
        $option = $this->option('model');
        if (is_string($option)) {
            $option = trim($option);
        }

        if (is_string($option) && $option !== '') {
            return $option;
        }

        $configured = config('atlas-agent-cli.model.'.self::PROVIDER);

        if (! is_string($configured)) {
            $configured = config('atlas-agent-cli.model');

            if (is_array($configured)) {
                $configured = $configured[self::PROVIDER] ?? null;
            }
        }

        $configured = is_string($configured) ? trim($configured) : $configured;

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    private function resolveInstructionsOption(): ?string
    {
        $option = $this->option('instructions');
        if (is_string($option)) {
            $option = trim($option);
        }

        return is_string($option) && $option !== '' ? $option : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveMetaOption(): ?array
    {
        $option = $this->option('meta');
        if (! is_string($option)) {
            return null;
        }

        $trimmed = trim($option);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('The --meta option must be valid JSON.');
        }

        return $decoded;
    }

    private function resolveResumeOption(): ?string
    {
        $option = $this->option('resume');
        if (is_string($option)) {
            $option = trim($option);
        }

        return is_string($option) && $option !== '' ? $option : null;
    }

    private function resolveWorkspaceOption(): ?string
    {
        $option = $this->option('workspace');
        if (is_string($option)) {
            $option = trim($option);
        }

        return is_string($option) && $option !== '' ? $option : null;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function shouldInjectFlag(array $arguments, string $flag): bool
    {
        foreach ($arguments as $argument) {
            if ($argument === $flag) {
                return false;
            }

            if (str_starts_with($argument, $flag.'=')) {
                return false;
            }
        }

        return true;
    }
}
