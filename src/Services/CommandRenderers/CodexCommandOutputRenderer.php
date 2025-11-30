<?php

declare(strict_types=1);

namespace Atlas\Agent\Services\CommandRenderers;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Class CodexCommandOutputRenderer
 *
 * Formats Codex process output for the console command and captures usage lines.
 */
class CodexCommandOutputRenderer implements SessionCommandOutputRenderer
{
    private string $lastUsageLine = '';

    public function provider(): string
    {
        return 'codex';
    }

    public function reset(): void
    {
        $this->lastUsageLine = '';
    }

    public function lastUsageLine(): string
    {
        return $this->lastUsageLine;
    }

    /**
     * @return callable(string, string): void
     */
    public function buildHandler(Command $command): callable
    {
        $this->reset();

        return function (string $type, string $message) use ($command): void {
            $lines = array_values(array_filter(
                array_map(static fn (string $line): string => trim($line), explode("\n", $message)),
                static fn (string $line): bool => $line !== ''
            ));

            foreach ($lines as $line) {
                if (str_contains($line, 'tokens used:')) {
                    $this->lastUsageLine = $line;

                    continue;
                }

                if ($type === Process::OUT && str_contains($line, '(exit code ')) {
                    preg_match('/\\(exit code\\s+(-?\\d+)\\)/', $line, $matches);
                    $exitCode = isset($matches[1]) ? (int) $matches[1] : 0;
                    if ($exitCode !== 0) {
                        $command->error($line);

                        continue;
                    }
                }

                if ($type === Process::ERR) {
                    $command->error($line);

                    continue;
                }

                if (str_starts_with($line, '- session started') || str_starts_with($line, '- session completed')) {
                    $command->info($line);
                } else {
                    $command->line($line);
                }
            }
        };
    }
}
