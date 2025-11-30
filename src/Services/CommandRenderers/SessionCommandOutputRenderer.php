<?php

declare(strict_types=1);

namespace Atlas\Agent\Services\CommandRenderers;

use Illuminate\Console\Command;

/**
 * Interface SessionCommandOutputRenderer
 *
 * Renders streamed session output for a specific provider to the console command.
 */
interface SessionCommandOutputRenderer
{
    public function provider(): string;

    public function reset(): void;

    public function lastUsageLine(): string;

    /**
     * @return callable(string, string): void
     */
    public function buildHandler(Command $command): callable;
}
