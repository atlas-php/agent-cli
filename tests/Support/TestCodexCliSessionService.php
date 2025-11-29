<?php

declare(strict_types=1);

namespace Atlas\Agent\Tests\Support;

use Atlas\Agent\Services\CodexCliSessionService;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Class TestCodexCliSessionService
 *
 * Test double that injects pre-configured Symfony Process instances for deterministic assertions.
 */
class TestCodexCliSessionService extends CodexCliSessionService
{
    public ?Process $headlessProcess = null;

    public ?Process $interactiveProcess = null;

    /**
     * @var array<string, string>
     */
    public array $streamedOutput = [];

    /**
     * @param  array<int, string>  $arguments
     */
    protected function buildProcess(array $arguments, bool $interactive, ?string $resumeThreadId = null): Process
    {
        if ($interactive) {
            if ($this->interactiveProcess === null) {
                throw new RuntimeException('Interactive process stub was not provided.');
            }

            return $this->interactiveProcess;
        }

        if ($this->headlessProcess === null) {
            throw new RuntimeException('Headless process stub was not provided.');
        }

        return $this->headlessProcess;
    }

    protected function streamToTerminal(string $type, string $cleanBuffer): void
    {
        $this->streamedOutput[$type] = ($this->streamedOutput[$type] ?? '').$cleanBuffer;
    }
}
