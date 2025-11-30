<?php

declare(strict_types=1);

namespace Atlas\Agent\Tests\Feature;

use Atlas\Agent\Services\CommandRenderers\CodexCommandOutputRenderer;
use Atlas\Agent\Tests\Support\FakeConsoleCommand;
use Atlas\Agent\Tests\TestCase;
use Symfony\Component\Process\Process;

/**
 * Class CodexCommandOutputRendererTest
 *
 * Ensures Codex output rendering routes lines to the correct console methods.
 */
final class CodexCommandOutputRendererTest extends TestCase
{
    public function test_renderer_tracks_usage_and_streams_lines(): void
    {
        $renderer = new CodexCommandOutputRenderer;
        $command = new FakeConsoleCommand;
        $handler = $renderer->buildHandler($command);

        $handler(Process::OUT, "- session started\nfirst line\n");
        $handler(Process::OUT, "tokens used: 1,234\n");
        $handler(Process::ERR, "problem line\n");
        $handler(Process::OUT, "- session completed (exit code 0)\nregular output\n");

        $this->assertSame('tokens used: 1,234', $renderer->lastUsageLine());
        $this->assertSame(['- session started', '- session completed (exit code 0)'], $command->infos);
        $this->assertSame(['first line', 'regular output'], $command->lines);
        $this->assertSame(['problem line'], $command->errors);
    }
}
